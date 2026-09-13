<?php

namespace Tests\Feature;

use App\FinanceType;
use App\Models\BudgetItem;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use App\TripFinance;
use App\TripStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripFinanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_trip_form_cannot_bypass_settlement_or_change_financial_customer(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 100000]);
        $other = Trip::factory()->create();
        $data = [
            'name' => $trip->name, 'customer_id' => $trip->customer_id, 'destination' => $trip->destination,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'participant_count' => 10, 'status' => 'closed',
        ];
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->put(route('super-admin.trips.update', $trip), $data)->assertSessionHasNoErrors();
        $this->assertSame(TripStatus::Draft, $trip->fresh()->status);
        $this->put(route('super-admin.trips.update', $trip), array_replace($data, ['status' => 'draft', 'customer_id' => $other->customer_id]))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame($trip->customer_id, $trip->fresh()->customer_id);
        $this->assertNull($trip->fresh()->finances_locked_at);
    }

    /** @return array<string, array{string, string, string}> */
    public static function adminEndpoints(): array
    {
        return [
            'finance page' => ['GET', 'trips.finance.show', 'trip'],
            'contract' => ['PUT', 'trips.finance.update', 'trip'],
            'create entry' => ['POST', 'trips.entries.store', 'trip'],
            'review entry' => ['PATCH', 'trips.entries.update', 'entry'],
            'add budget' => ['POST', 'trips.budgets.store', 'trip'],
            'edit budget' => ['PUT', 'trips.budgets.update', 'budget'],
            'archive budget' => ['DELETE', 'trips.budgets.destroy', 'budget'],
            'close' => ['POST', 'trips.settlement.store', 'trip'],
            'reopen' => ['DELETE', 'trips.settlement.destroy', 'trip'],
            'report' => ['GET', 'finance.index', 'none'],
            'export' => ['GET', 'finance.export', 'none'],
        ];
    }

    #[DataProvider('adminEndpoints')]
    public function test_guest_cannot_access_finance(string $method, string $route, string $target): void
    {
        $trip = Trip::factory()->create();
        $entry = FinanceEntry::factory()->for($trip)->create();
        $budget = BudgetItem::factory()->for($trip)->create();
        $parameters = match ($target) {
            'entry' => [$trip, $entry], 'budget' => [$trip, $budget], 'trip' => [$trip], default => []
        };

        $this->call($method, route('super-admin.'.$route, $parameters))->assertRedirectToRoute('login');

        $this->assertDatabaseCount('finance_entries', 1);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    #[DataProvider('adminEndpoints')]
    public function test_tl_cannot_access_admin_finance(string $method, string $route, string $target): void
    {
        $trip = Trip::factory()->create();
        $leader = User::factory()->tourLeader()->create();
        $trip->tourLeaders()->attach($leader);
        $entry = FinanceEntry::factory()->for($trip)->create();
        $budget = BudgetItem::factory()->for($trip)->create();
        $parameters = match ($target) {
            'entry' => [$trip, $entry], 'budget' => [$trip, $budget], 'trip' => [$trip], default => []
        };

        $this->actingAs($leader)->call($method, route('super-admin.'.$route, $parameters))->assertForbidden();

        $this->assertDatabaseCount('finance_audits', 0);
        $this->assertDatabaseCount('finance_entries', 1);
        $this->assertNotSoftDeleted($budget);
    }

    public function test_summary_separates_operating_costs_cash_movements_and_pending_expenses(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 1000000]);
        $leader = User::factory()->tourLeader()->create();
        $admin = User::factory()->superAdmin()->create();
        foreach ([
            ['payment', 700000, null, 'approved'], ['expense', 100000, null, 'approved'],
            ['expense', 200000, $leader->id, 'approved'], ['advance', 150000, $leader->id, 'approved'],
            ['expense', 900000, $leader->id, 'pending'], ['expense', 900000, null, 'void'],
            ['expense', 900000, null, 'rejected'],
        ] as [$type, $amount, $leaderId, $status]) {
            FinanceEntry::factory()->for($trip)->create(['created_by' => $admin->id, 'type' => $type, 'amount' => $amount, 'leader_id' => $leaderId, 'status' => $status]);
        }
        BudgetItem::factory()->for($trip)->create(['quantity' => 5, 'unit_amount' => 10000]);

        $summary = (new TripFinance)->summary($trip);

        $this->assertSame(700000, $summary['payment']);
        $this->assertSame(300000, $summary['expense']);
        $this->assertSame(450000, $summary['cash']);
        $this->assertSame(700000, $summary['profit']);
        $this->assertSame(300000, $summary['receivable']);
        $this->assertSame(50000, $summary['budget']);
        $this->assertSame(900000, $summary['pending']);
        $this->assertSame(-50000, $summary['balances'][$leader->id]);
    }

    public function test_contract_changes_are_audited_and_cannot_undercut_payments(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 1000000]);
        FinanceEntry::factory()->for($trip)->create(['type' => FinanceType::Payment, 'amount' => 500000]);
        $this->actingAs(User::factory()->superAdmin()->create());
        $url = route('super-admin.trips.finance.update', $trip);

        $this->put($url, ['contract_amount' => 1500000, 'reason' => 'Tambah peserta'])->assertSessionHasNoErrors();
        $this->assertSame(1500000, $trip->fresh()->contract_amount);
        $this->put($url, ['contract_amount' => 499999, 'reason' => 'Tidak valid'])->assertSessionHasErrors('contract_amount');
        $this->put($url, ['contract_amount' => -1, 'reason' => 'Tidak valid'])->assertSessionHasErrors('contract_amount');

        $this->assertDatabaseCount('finance_audits', 1);
        $this->assertDatabaseHas('finance_audits', ['action' => 'contract.updated', 'reason' => 'Tambah peserta']);
    }

    public function test_admin_manages_rab_with_integer_totals_and_preserved_archive(): void
    {
        $trip = Trip::factory()->create();
        $data = ['description' => 'Bus', 'category' => 'transport', 'quantity' => 3, 'unit_amount' => 1000000, 'trip_id' => 99999];
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.budgets.store', $trip), $data)->assertSessionHasNoErrors();
        $item = BudgetItem::query()->sole();
        $this->assertSame($trip->id, $item->trip_id);
        $this->assertSame(3000000, $item->total());
        $this->put(route('super-admin.trips.budgets.update', [$trip, $item]), array_replace($data, ['quantity' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(2000000, $item->fresh()->total());
        $this->delete(route('super-admin.trips.budgets.destroy', [$trip, $item]), ['reason' => 'Transport dibatalkan'])->assertSessionHasNoErrors();

        $this->assertSoftDeleted($item);
        $this->assertSame(0, (new TripFinance)->summary($trip)['budget']);
        $this->assertDatabaseCount('finance_audits', 3);
    }

    public function test_invalid_budget_and_foreign_nested_budget_are_rejected(): void
    {
        $trip = Trip::factory()->create();
        $item = BudgetItem::factory()->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.budgets.store', $trip), ['quantity' => 0, 'unit_amount' => 1.5, 'category' => 'fake'])
            ->assertSessionHasErrors(['quantity', 'unit_amount', 'category', 'description']);
        $this->put(route('super-admin.trips.budgets.update', [$trip, $item]), [])->assertNotFound();
        $this->delete(route('super-admin.trips.budgets.destroy', [$trip, $item]), ['reason' => 'Attack'])->assertNotFound();

        $this->assertNotSoftDeleted($item);
        $this->assertDatabaseCount('budget_items', 1);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    /** @return array<string, array{string}> */
    public static function unsettledCases(): array
    {
        return ['missing contract' => ['contract'], 'unpaid' => ['unpaid'], 'pending expense' => ['pending'], 'tl balance' => ['balance'], 'opposite tl balances' => ['opposite']];
    }

    #[DataProvider('unsettledCases')]
    public function test_settlement_refuses_unfinished_finances(string $condition): void
    {
        $trip = Trip::factory()->create(['contract_amount' => $condition === 'contract' ? null : 0]);
        if ($condition === 'unpaid') {
            $trip->forceFill(['contract_amount' => 100000])->save();
        }
        if ($condition === 'pending') {
            FinanceEntry::factory()->for($trip)->create(['status' => 'pending']);
        }
        if (in_array($condition, ['balance', 'opposite'], true)) {
            FinanceEntry::factory()->for($trip)->create(['type' => 'advance', 'leader_id' => User::factory()->tourLeader()]);
        }
        if ($condition === 'opposite') {
            FinanceEntry::factory()->for($trip)->create(['type' => 'expense', 'leader_id' => User::factory()->tourLeader()]);
        }

        $this->actingAs(User::factory()->superAdmin()->create())->post(route('super-admin.trips.settlement.store', $trip), ['reason' => 'Tutup'])
            ->assertSessionHasErrors('finance');

        $this->assertNull($trip->fresh()->finances_locked_at);
        $this->assertDatabaseCount('finance_audits', 0);
    }

    public function test_settlement_locks_every_write_and_reopening_requires_reason(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 100000]);
        $entry = FinanceEntry::factory()->for($trip)->create(['type' => 'payment', 'amount' => 100000]);
        $item = BudgetItem::factory()->for($trip)->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.settlement.store', $trip), ['reason' => 'Semua sudah sesuai'])->assertSessionHasNoErrors();
        $this->assertNotNull($trip->fresh()->finances_locked_at);
        $this->assertSame(TripStatus::Closed, $trip->fresh()->status);
        $this->put(route('super-admin.trips.finance.update', $trip), ['contract_amount' => 200000, 'reason' => 'Attack'])->assertSessionHasErrors('finance');
        $this->patch(route('super-admin.trips.entries.update', [$trip, $entry]), ['status' => 'void', 'reason' => 'Attack'])->assertSessionHasErrors('finance');
        $budgetData = ['description' => 'Attack', 'category' => 'other', 'quantity' => 1, 'unit_amount' => 10];
        $this->post(route('super-admin.trips.budgets.store', $trip), $budgetData)->assertSessionHasErrors('finance');
        $this->put(route('super-admin.trips.budgets.update', [$trip, $item]), $budgetData)->assertSessionHasErrors('finance');
        $this->delete(route('super-admin.trips.budgets.destroy', [$trip, $item]), ['reason' => 'Attack'])->assertSessionHasErrors('finance');
        $this->put(route('super-admin.trips.update', $trip), [
            'name' => 'Attack', 'customer_id' => $trip->customer_id, 'destination' => $trip->destination,
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'participant_count' => 2, 'status' => 'draft',
        ])->assertSessionHasErrors('status');
        $this->delete(route('super-admin.trips.settlement.destroy', $trip), [])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('finance_audits', 1);
        $this->assertSame('approved', $entry->fresh()->status);
        $this->assertNotSoftDeleted($item);

        $this->delete(route('super-admin.trips.settlement.destroy', $trip), ['reason' => 'Koreksi bukti'])->assertSessionHasNoErrors();

        $this->assertNull($trip->fresh()->finances_locked_at);
        $this->assertSame(TripStatus::AwaitingSettlement, $trip->fresh()->status);
        $this->assertDatabaseHas('finance_audits', ['action' => 'settlement.reopened', 'reason' => 'Koreksi bukti']);
    }

    public function test_tl_page_exposes_only_own_expenses_and_balances_not_contract_payments_or_margin(): void
    {
        $trip = Trip::factory()->create(['contract_amount' => 888888888]);
        $leader = User::factory()->tourLeader()->create();
        $trip->tourLeaders()->attach($leader);
        FinanceEntry::factory()->for($trip)->create(['description' => 'SECRET CUSTOMER PAYMENT', 'type' => 'payment']);
        FinanceEntry::factory()->for($trip)->create(['description' => 'OTHER LEADER EXPENSE', 'leader_id' => User::factory()->tourLeader()]);
        FinanceEntry::factory()->for($trip)->create(['description' => 'My taxi receipt', 'leader_id' => $leader->id]);

        $this->actingAs($leader)->get(route('tour-leader.trips.finance', $trip))
            ->assertSee('My taxi receipt')->assertDontSee('SECRET CUSTOMER PAYMENT')->assertDontSee('OTHER LEADER EXPENSE')
            ->assertDontSee('888.888.888')->assertDontSee('Margin kontrak sementara')->assertDontSee('Histori keuangan');
    }

    public function test_tl_can_read_rejection_reason_without_raw_audit_source(): void
    {
        $leader = User::factory()->tourLeader()->create();
        $admin = User::factory()->superAdmin()->create();
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);
        $trip->tourLeaders()->attach($leader);
        FinanceEntry::factory()->for($trip)->create([
            'leader_id' => $leader->id,
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_reason' => 'Nota tidak terbaca, mohon foto ulang.',
        ]);

        $this->actingAs($leader)->get(route('tour-leader.trips.finance', $trip))
            ->assertOk()->assertSee('Alasan pengajuan ditolak')
            ->assertSee('Nota tidak terbaca, mohon foto ulang.');

        (new TripFinance)->audit($trip, $admin, 'entry.rejected', ['entry_id' => 99, 'amount' => 100000], 'Contoh audit');
        $this->actingAs($admin)->get(route('super-admin.trips.finance.show', $trip))
            ->assertSee('Pengeluaran ditolak')->assertSee('Transaksi #99')->assertDontSee('entry_id');
    }

    public function test_inactive_existing_session_cannot_submit_expenses(): void
    {
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create();
        $trip->tourLeaders()->attach($leader);
        $this->actingAs($leader);
        $leader->update(['is_active' => false]);

        $this->post(route('tour-leader.trips.expenses.store', $trip), [])->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseCount('finance_entries', 0);
    }

    public function test_admin_finance_view_renders_budgets_audits_and_escaped_descriptions(): void
    {
        $trip = Trip::factory()->create();
        BudgetItem::factory()->for($trip)->create();
        FinanceEntry::factory()->for($trip)->create(['description' => '<script>alert(1)</script>']);
        $admin = User::factory()->superAdmin()->create();
        (new TripFinance)->audit($trip, $admin, 'test', [], '<script>alert(2)</script>');

        $this->actingAs($admin)->get(route('super-admin.trips.finance.show', $trip))
            ->assertSee('Rencana anggaran biaya')->assertSee('Histori keuangan')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<script>alert(2)</script>', false);
    }
}
