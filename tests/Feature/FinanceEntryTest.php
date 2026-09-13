<?php

namespace Tests\Feature;

use App\FinanceType;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use App\TripFinance;
use App\TripStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinanceEntryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_locked_trip_rejects_new_entries_before_storing_receipts(): void
    {
        Storage::fake('receipts');
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing, 'finances_locked_at' => now()]);
        $leader = User::factory()->tourLeader()->create();
        $trip->tourLeaders()->attach($leader);
        $payload = $this->leaderPayload();

        $this->actingAs($leader)->post(route('tour-leader.trips.expenses.store', $trip), $payload)->assertSessionHasErrors('finance');
        $this->actingAs(User::factory()->superAdmin()->create())->post(route('super-admin.trips.entries.store', $trip), $this->payload())->assertSessionHasErrors('finance');

        $this->assertDatabaseCount('finance_entries', 0);
        $this->assertDatabaseCount('finance_audits', 0);
        Storage::disk('receipts')->assertDirectoryEmpty('/');
    }

    public function test_guest_cannot_read_receipts_or_submit_tl_expenses(): void
    {
        $entry = FinanceEntry::factory()->create();

        $this->get(route('entries.receipt', [$entry->trip, $entry]))->assertRedirectToRoute('login');
        $this->get(route('tour-leader.trips.finance', $entry->trip))->assertRedirectToRoute('login');
        $this->post(route('tour-leader.trips.expenses.store', $entry->trip), [])->assertRedirectToRoute('login');

        $this->assertDatabaseCount('finance_entries', 1);
    }

    public function test_duplicate_token_cannot_be_reused_on_another_trip(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $entry = FinanceEntry::factory()->create(['created_by' => $admin->id]);
        $otherTrip = Trip::factory()->create();

        $this->actingAs($admin)->post(route('super-admin.trips.entries.store', $otherTrip),
            $this->payload(['submission_token' => $entry->submission_token]))->assertConflict();

        $this->assertDatabaseCount('finance_entries', 1);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'expense', 'category' => 'meals', 'occurred_on' => '2026-01-01',
            'amount' => 250000, 'description' => 'Makan siang peserta',
            'submission_token' => (string) Str::uuid(),
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function leaderPayload(array $overrides = []): array
    {
        $receipt = UploadedFile::fake()->image('receipt.jpg', 800, 600)->size(120);

        return $this->payload(array_replace([
            'receipt_data' => 'data:image/jpeg;base64,'.base64_encode($receipt->getContent()),
            'captured_at' => now()->subMinute()->toISOString(),
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'location_accuracy' => 8.5,
            'receipt_original_size' => 4200000,
        ], $overrides));
    }

    public function test_admin_records_payment_without_double_submission_and_ignores_protected_fields(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trip = Trip::factory()->create(['contract_amount' => 1000000]);
        $data = $this->payload(['type' => 'payment', 'amount' => 400000, 'status' => 'void', 'created_by' => 99999, 'trip_id' => 99999]);

        $this->actingAs($admin)->post(route('super-admin.trips.entries.store', $trip), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('super-admin.trips.entries.store', $trip), $data)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('finance_entries', 1);
        $this->assertDatabaseCount('finance_audits', 2);
        $this->assertSame(TripStatus::Confirmed, $trip->fresh()->status);
        $this->assertDatabaseHas('finance_entries', ['trip_id' => $trip->id, 'created_by' => $admin->id, 'type' => 'payment', 'amount' => 400000, 'status' => 'approved', 'leader_id' => null, 'category' => null]);
    }

    public function test_payment_requires_contract_and_cannot_exceed_remaining_bill(): void
    {
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.entries.store', $trip), $this->payload(['type' => 'payment']))
            ->assertSessionHasErrors(['amount' => 'Isi nilai kontrak sebelum mencatat pembayaran.']);
        $trip->forceFill(['contract_amount' => 100000])->save();
        $this->post(route('super-admin.trips.entries.store', $trip), $this->payload(['type' => 'payment']))
            ->assertSessionHasErrors(['amount' => 'Pembayaran melebihi sisa tagihan customer.']);

        $this->assertDatabaseCount('finance_entries', 0);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    public function test_leader_submits_pending_expense_with_private_receipt_and_cannot_self_approve(): void
    {
        Storage::fake('receipts');
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);
        $trip->tourLeaders()->attach($leader);
        $data = $this->leaderPayload(['leader_id' => 99999, 'status' => 'approved']);

        $this->actingAs($leader)->post(route('tour-leader.trips.expenses.store', $trip), $data)->assertSessionHasNoErrors()->assertRedirect();

        $entry = FinanceEntry::query()->sole();
        $this->assertSame('pending', $entry->status);
        $this->assertSame($leader->id, $entry->leader_id);
        $this->assertSame($leader->id, $entry->created_by);
        $this->assertNotNull($entry->captured_at);
        $this->assertSame('-6.2000000', $entry->latitude);
        $this->assertNotNull($entry->receipt_stored_size);
        Storage::disk('receipts')->assertExists($entry->receipt_path);
        $this->assertSame(0, (new TripFinance)->summary($trip)['expense']);
        $this->get(route('entries.receipt', [$trip, $entry]))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->get(route('entries.receipt', ['trip' => $trip, 'entry' => $entry, 'download' => 1]))
            ->assertDownload('bukti-'.$entry->id.'.jpg');
        $this->patch(route('super-admin.trips.entries.update', [$trip, $entry]), ['status' => 'approved', 'reason' => 'self'])->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->status);
    }

    public function test_tl_camera_uses_direct_webcam_and_stores_normalized_jpeg(): void
    {
        Storage::fake('receipts');
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);
        $trip->tourLeaders()->attach($leader);
        $this->actingAs($leader)->get(route('tour-leader.trips.finance', $trip))
            ->assertSee('data-camera-video', false)
            ->assertSee('data-camera-start', false)
            ->assertSee('data-receipt-data', false)
            ->assertDontSee('type="file"', false);

        $this->post(
            route('tour-leader.trips.expenses.store', $trip),
            $this->leaderPayload(),
        )->assertSessionHasNoErrors();

        $entry = FinanceEntry::query()->sole();
        Storage::disk('receipts')->assertExists($entry->receipt_path);
        $this->assertSame('jpg', pathinfo($entry->receipt_path, PATHINFO_EXTENSION));
    }

    public function test_leader_cannot_submit_expense_until_trip_is_ongoing(): void
    {
        Storage::fake('receipts');
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create(['status' => TripStatus::Draft]);
        $trip->tourLeaders()->attach($leader);

        $this->actingAs($leader)->get(route('tour-leader.trips.finance', $trip))
            ->assertOk()->assertDontSee('Ajukan pengeluaran')->assertSee('Pengajuan pengeluaran belum tersedia');
        $this->post(route('tour-leader.trips.expenses.store', $trip), $this->leaderPayload())
            ->assertSessionHasErrors('finance');

        $this->assertDatabaseCount('finance_entries', 0);
        Storage::disk('receipts')->assertDirectoryEmpty('/');
    }

    public function test_admin_approves_rejects_and_voids_with_audit_without_deleting_entries(): void
    {
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);
        $entry = FinanceEntry::factory()->for($trip)->create(['status' => 'pending']);
        $rejected = FinanceEntry::factory()->for($trip)->create(['status' => 'pending']);
        $this->actingAs(User::factory()->superAdmin()->create());
        $url = route('super-admin.trips.entries.update', [$trip, $entry]);

        $this->patch($url, ['status' => 'approved'])->assertSessionHasErrors('reason');
        $this->patch($url, ['status' => 'approved', 'reason' => 'Bukti sesuai'])->assertSessionHasNoErrors();
        $this->assertSame(100000, (new TripFinance)->summary($trip)['expense']);
        $this->patch($url, ['status' => 'approved', 'reason' => 'Duplikat'])->assertSessionHasErrors('status');
        $this->patch($url, ['status' => 'void', 'reason' => 'Salah nominal'])->assertSessionHasNoErrors();
        $this->patch(route('super-admin.trips.entries.update', [$trip, $rejected]), ['status' => 'rejected', 'reason' => 'Bukan biaya trip'])->assertSessionHasNoErrors();

        $this->assertSame('void', $entry->fresh()->status);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('Bukan biaya trip', $rejected->fresh()->review_reason);
        $this->assertNotNull($rejected->fresh()->reviewed_at);
        $this->assertSame(0, (new TripFinance)->summary($trip)['expense']);
        $this->assertDatabaseCount('finance_entries', 2);
        $this->assertDatabaseCount('finance_audits', 3);
        $this->assertDatabaseHas('finance_audits', ['action' => 'entry.void', 'reason' => 'Salah nominal']);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidValues(): array
    {
        return [
            'zero' => ['amount', 0], 'negative' => ['amount', -1], 'fraction' => ['amount', 1.5],
            'formatted' => ['amount', '1.000'], 'overflow' => ['amount', 1000000000001],
            'type' => ['type', 'profit'], 'category' => ['category', 'unknown'],
            'missing description' => ['description', ''], 'long description' => ['description', str_repeat('a', 501)],
            'date' => ['occurred_on', 'bad-date'], 'future date' => ['occurred_on', '2099-01-01'],
            'token' => ['submission_token', 'not-a-uuid'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_entries_have_no_side_effects(string $field, mixed $value): void
    {
        $trip = Trip::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.trips.entries.store', $trip), $this->payload([$field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('finance_entries', 0);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    public function test_tl_cannot_record_payments_and_requires_safe_receipt(): void
    {
        Storage::fake('receipts');
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create();
        $trip->tourLeaders()->attach($leader);
        $this->actingAs($leader);
        $url = route('tour-leader.trips.expenses.store', $trip);

        $this->post($url, $this->leaderPayload(['type' => 'payment']))->assertSessionHasErrors('type');
        $unsafeReceipt = 'data:image/jpeg;base64,'.base64_encode('<svg onload="alert(1)"></svg>');
        $this->post($url, $this->leaderPayload(['receipt_data' => $unsafeReceipt]))->assertSessionHasErrors('receipt_data');
        $this->post($url, $this->payload([
            'receipt' => UploadedFile::fake()->image('bypass.jpg'),
            'captured_at' => now()->subMinute()->toISOString(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'location_accuracy' => 8,
            'receipt_original_size' => 1000,
        ]))->assertSessionHasErrors('receipt_data');

        $this->assertDatabaseCount('finance_entries', 0);
        Storage::disk('receipts')->assertDirectoryEmpty('/');
    }

    public function test_cross_trip_and_other_leaders_receipts_are_not_accessible(): void
    {
        Storage::fake('receipts');
        $leader = User::factory()->tourLeader()->create();
        $other = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create();
        $foreign = Trip::factory()->create();
        $trip->tourLeaders()->attach($leader);
        $entry = FinanceEntry::factory()->for($trip)->create(['leader_id' => $other->id, 'receipt_path' => 'private.pdf']);
        Storage::disk('receipts')->put('private.pdf', '%PDF-private');

        $this->actingAs($leader)->get(route('entries.receipt', [$trip, $entry]))->assertNotFound();
        $this->get(route('entries.receipt', [$foreign, $entry]))->assertNotFound();
        $this->get(route('tour-leader.trips.finance', $foreign))->assertNotFound();
        $this->post(route('tour-leader.trips.expenses.store', $foreign), [])->assertNotFound();
        $this->actingAs(User::factory()->superAdmin()->create())->patch(route('super-admin.trips.entries.update', [$foreign, $entry]), ['status' => 'void', 'reason' => 'Attack'])->assertNotFound();
        $this->assertSame('approved', $entry->fresh()->status);
        Storage::disk('receipts')->assertExists('private.pdf');
    }

    public function test_only_current_active_tl_can_receive_advance_but_old_inactive_tl_can_settle(): void
    {
        $trip = Trip::factory()->create();
        $old = User::factory()->tourLeader()->inactive()->create();
        $current = User::factory()->tourLeader()->create();
        $trip->tourLeaders()->attach($current);
        FinanceEntry::factory()->for($trip)->create(['type' => FinanceType::Advance, 'category' => null, 'leader_id' => $old->id, 'amount' => 100000]);
        $this->actingAs(User::factory()->superAdmin()->create());
        $url = route('super-admin.trips.entries.store', $trip);

        $this->post($url, $this->payload(['type' => 'advance', 'leader_id' => $old->id]))->assertSessionHasErrors('leader_id');
        $this->post($url, $this->payload(['type' => 'advance', 'leader_id' => $current->id]))->assertSessionHasNoErrors();
        $this->post($url, $this->payload(['type' => 'return', 'leader_id' => $old->id, 'amount' => 100000]))->assertSessionHasNoErrors();

        $this->assertSame(0, (new TripFinance)->summary($trip)['balances'][$old->id]);
        $this->assertDatabaseCount('finance_entries', 3);
    }

    public function test_non_tl_and_unassigned_leader_cannot_be_financial_counterparties(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $trip = Trip::factory()->create();
        $unassigned = User::factory()->tourLeader()->create();
        $this->actingAs($admin);

        foreach ([$admin->id, $unassigned->id, 999999] as $id) {
            $this->post(route('super-admin.trips.entries.store', $trip), $this->payload(['type' => 'advance', 'leader_id' => $id]))
                ->assertSessionHasErrors('leader_id');
        }
        $this->post(route('super-admin.trips.entries.store', $trip), $this->payload(['type' => 'advance']))->assertSessionHasErrors('leader_id');

        $this->assertDatabaseCount('finance_entries', 0);
    }

    public function test_return_and_reimbursement_cannot_exceed_balance(): void
    {
        $trip = Trip::factory()->create();
        $leader = User::factory()->tourLeader()->create();
        $trip->tourLeaders()->attach($leader);
        FinanceEntry::factory()->for($trip)->create(['type' => FinanceType::Expense, 'leader_id' => $leader->id, 'amount' => 50000]);
        $this->actingAs(User::factory()->superAdmin()->create());
        $url = route('super-admin.trips.entries.store', $trip);

        $this->post($url, $this->payload(['type' => 'return', 'leader_id' => $leader->id, 'amount' => 1]))->assertSessionHasErrors('amount');
        $this->post($url, $this->payload(['type' => 'reimbursement', 'leader_id' => $leader->id, 'amount' => 50001]))->assertSessionHasErrors('amount');
        $this->post($url, $this->payload(['type' => 'reimbursement', 'leader_id' => $leader->id, 'amount' => 50000]))->assertSessionHasNoErrors();

        $this->assertSame(0, (new TripFinance)->summary($trip)['balances'][$leader->id]);
        $this->assertDatabaseCount('finance_entries', 2);
    }

    public function test_no_hard_delete_or_amount_update_endpoint_exists(): void
    {
        $entry = FinanceEntry::factory()->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->delete(route('super-admin.trips.entries.update', [$entry->trip, $entry]))->assertMethodNotAllowed();
        $this->patch(route('super-admin.trips.entries.update', [$entry->trip, $entry]), ['amount' => 1])->assertSessionHasErrors(['status', 'reason']);

        $this->assertSame(100000, $entry->fresh()->amount);
    }
}
