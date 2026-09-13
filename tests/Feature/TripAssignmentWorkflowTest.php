<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Trip;
use App\Models\TripAssignment;
use App\Models\User;
use App\TripAssignmentStatus;
use App\TripStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TripAssignmentWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_payment_and_assignment_acceptance_drive_trip_workflow(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $leader = User::factory()->tourLeader()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)->post(route('super-admin.trips.store'), [
            'name' => 'Open Trip Raja Ampat',
            'customer_id' => $customer->id,
            'destination' => 'Raja Ampat',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'participant_count' => 12,
            'tour_leader_id' => $leader->id,
            'status' => TripStatus::Ongoing->value,
        ])->assertSessionHasNoErrors();

        $trip = Trip::query()->sole();
        $assignment = TripAssignment::query()->sole();
        $this->assertSame(TripStatus::Draft, $trip->status);
        $this->assertSame(TripAssignmentStatus::Pending, $assignment->status);
        $this->actingAs($leader)->get(route('tour-leader.index'))
            ->assertSee('Undangan tugas baru')->assertSee($trip->name);
        $this->post(route('tour-leader.assignments.accept', $assignment))
            ->assertSessionHasErrors('assignment');

        $trip->forceFill(['contract_amount' => 1000000])->save();
        $this->actingAs($admin)->post(route('super-admin.trips.entries.store', $trip), [
            'type' => 'payment',
            'amount' => 250000,
            'occurred_on' => '2026-09-01',
            'description' => 'DP customer',
            'submission_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(TripStatus::Confirmed, $trip->fresh()->status);

        $this->actingAs($leader)->post(route('tour-leader.assignments.accept', $assignment))
            ->assertSessionHasNoErrors();
        $this->assertSame(TripAssignmentStatus::Accepted, $assignment->fresh()->status);
        $this->assertSame(TripStatus::Ongoing, $trip->fresh()->status);
        $this->get(route('tour-leader.trips.finance', $trip))->assertOk()->assertSee('Ajukan pengeluaran');
    }

    public function test_leader_can_reject_assignment_with_reason_and_other_leader_cannot_respond(): void
    {
        $leader = User::factory()->tourLeader()->create();
        $otherLeader = User::factory()->tourLeader()->create();
        $assignment = TripAssignment::factory()->for($leader, 'user')->pending()->create();

        $this->actingAs($otherLeader)->delete(route('tour-leader.assignments.reject', $assignment), [
            'response_note' => 'Tidak berhak',
        ])->assertNotFound();

        $this->actingAs($leader)->delete(route('tour-leader.assignments.reject', $assignment), [
            'response_note' => 'Jadwal berbenturan dengan tugas lain.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trip_assignments', [
            'id' => $assignment->id,
            'status' => TripAssignmentStatus::Rejected->value,
            'response_note' => 'Jadwal berbenturan dengan tugas lain.',
        ]);
        $this->get(route('tour-leader.index'))->assertSee('Jadwal berbenturan dengan tugas lain.');
    }
}
