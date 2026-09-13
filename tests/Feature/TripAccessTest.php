<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripAssignment;
use App\Models\User;
use App\TripAssignmentStatus;
use App\TripStatus;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(Trip $trip, ?User $leader): array
    {
        return [
            'name' => $trip->name, 'customer_id' => $trip->customer_id, 'destination' => $trip->destination,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
            'participant_count' => 20, 'status' => TripStatus::Draft->value,
            'tour_leader_id' => $leader?->id,
        ];
    }

    public function test_admin_can_assign_active_reusable_tour_leader_during_create_and_edit(): void
    {
        $leader = User::factory()->tourLeader()->create();
        $trip = Trip::factory()->create();
        $payload = $this->payload($trip, $leader);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.store'), $payload)->assertSessionHasNoErrors();
        $this->put(route('super-admin.trips.update', $trip), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trip_assignments', 2);
        $this->assertSame(0, $leader->assignedTrips()->count());
        $this->assertSame(2, $leader->tripAssignments()->count());
        $this->assertDatabaseHas('trip_assignments', ['trip_id' => $trip->id, 'user_id' => $leader->id, 'status' => TripAssignmentStatus::Pending->value]);
        $this->assertSame(UserRole::TourLeader, $leader->fresh()->role);
    }

    /** @return array<string, array{bool, bool}> */
    public static function invalidLeaders(): array
    {
        return ['inactive TL create' => [false, false], 'admin create' => [true, false],
            'inactive TL edit' => [false, true], 'admin edit' => [true, true]];
    }

    #[DataProvider('invalidLeaders')]
    public function test_inactive_and_non_tl_assignments_are_rejected(bool $nonTl, bool $edit): void
    {
        $leader = $nonTl ? User::factory()->superAdmin()->create() : User::factory()->tourLeader()->inactive()->create();
        $trip = Trip::factory()->create();
        $payload = $this->payload($trip, $leader);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->call($edit ? 'PUT' : 'POST', route('super-admin.trips.'.($edit ? 'update' : 'store'), $edit ? $trip : []), $payload)
            ->assertSessionHasErrors('tour_leader_id');

        $this->assertDatabaseCount('trip_assignments', 0);
        $this->assertDatabaseCount('trips', 1);
    }

    public function test_changing_assignment_revokes_old_leader_access_and_preserves_trip(): void
    {
        $oldLeader = User::factory()->tourLeader()->create();
        $newLeader = User::factory()->tourLeader()->create();
        $assignment = TripAssignment::factory()->for($oldLeader, 'user')->create();
        $trip = $assignment->trip;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('super-admin.trips.update', $trip), $this->payload($trip, $newLeader))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trip_assignments', 1);
        $this->assertDatabaseHas('trip_assignments', ['trip_id' => $trip->id, 'user_id' => $newLeader->id]);
        $this->actingAs($oldLeader)->get(route('tour-leader.trips.show', $trip))->assertNotFound();
        $this->actingAs($newLeader)->get(route('tour-leader.trips.show', $trip))->assertNotFound();
        $this->get(route('tour-leader.index'))->assertOk()->assertSee($trip->name)->assertSee('Undangan tugas baru');
        $this->assertSame($trip->code, $trip->fresh()->code);
    }

    public function test_assignment_can_be_cleared_without_deleting_trip_or_user(): void
    {
        $assignment = TripAssignment::factory()->create();
        $trip = $assignment->trip;
        $leader = $assignment->user;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('super-admin.trips.update', $trip), $this->payload($trip, null))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trip_assignments', 0);
        $this->assertModelExists($trip);
        $this->assertModelExists($leader);
        $this->actingAs($leader)->get(route('tour-leader.trips.show', $trip))->assertNotFound();
    }

    public function test_tl_sees_only_assigned_trips_and_details_are_read_only(): void
    {
        $assignment = TripAssignment::factory()->create();
        $own = $assignment->trip;
        $other = TripAssignment::factory()->create()->trip;
        $unassigned = Trip::factory()->create();
        $own->customer->update(['notes' => 'PRIVATE CUSTOMER NOTE', 'phone' => 'PRIVATE PHONE']);

        $this->actingAs($assignment->user)->get(route('tour-leader.index'))
            ->assertOk()->assertSee($own->code)->assertDontSee($other->code)->assertDontSee($unassigned->code);
        $this->get(route('tour-leader.trips.show', $own))->assertOk()->assertSee($own->code)
            ->assertSee($own->customer->name)->assertDontSee('PRIVATE CUSTOMER NOTE')->assertDontSee('PRIVATE PHONE')
            ->assertDontSee('Edit Trip');
        $this->get(route('tour-leader.trips.show', $other))->assertNotFound();
        $this->get(route('tour-leader.trips.show', $unassigned))->assertNotFound();
        $this->put('/tour-leader/trips/'.$own->id, ['name' => 'Tampered'])->assertMethodNotAllowed();
        $this->assertSame($own->name, $own->fresh()->name);
    }

    public function test_unassigned_tl_sees_empty_state(): void
    {
        Trip::factory()->create();

        $this->actingAs(User::factory()->tourLeader()->create())->get(route('tour-leader.index'))
            ->assertSee('Belum ada tugas aktif.');
    }

    public function test_guest_cannot_open_tl_trip_detail(): void
    {
        $trip = Trip::factory()->create();

        $this->get(route('tour-leader.trips.show', $trip))->assertRedirectToRoute('login');
    }

    public function test_admin_sees_all_trips_and_policy_checks_full_access_matrix(): void
    {
        $assignment = TripAssignment::factory()->create();
        $other = TripAssignment::factory()->create();
        $unassigned = Trip::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('super-admin.trips.index'))
            ->assertSee($assignment->trip->code)->assertSee($other->trip->code)->assertSee($unassigned->code);
        foreach ([$assignment->trip, $other->trip, $unassigned] as $trip) {
            $this->get(route('super-admin.trips.show', $trip))->assertOk();
            $this->assertTrue(Gate::forUser($admin)->allows('view', $trip));
        }
        $this->assertTrue(Gate::forUser($assignment->user)->allows('view', $assignment->trip));
        $this->assertFalse(Gate::forUser($assignment->user)->allows('view', $other->trip));
        $this->assertFalse(Gate::forUser($assignment->user)->allows('view', $unassigned));
        $this->assertFalse(Gate::forUser(User::factory()->tourLeader()->inactive()->create())->allows('view', $assignment->trip));
    }

    public function test_deactivation_after_login_terminates_session_and_keeps_assignment(): void
    {
        $assignment = TripAssignment::factory()->create();
        $leader = $assignment->user;
        $this->post(route('login.store'), ['email' => $leader->email, 'password' => 'password'])
            ->assertRedirectToRoute('tour-leader.index');

        $leader->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->get(route('tour-leader.trips.show', $assignment->trip))->assertForbidden();
        $this->assertGuest();
        $this->get(route('tour-leader.index'))->assertRedirectToRoute('login');
        $this->assertModelExists($assignment);
    }

    public function test_only_one_assignment_per_trip_is_enforced_by_database(): void
    {
        $assignment = TripAssignment::factory()->create();

        $this->expectException(QueryException::class);
        TripAssignment::factory()->for($assignment->trip)->create();
    }

    public function test_assigned_user_cannot_be_deleted(): void
    {
        $assignment = TripAssignment::factory()->create();

        $this->expectException(QueryException::class);
        $assignment->user->delete();
    }

    public function test_assigned_trip_cannot_be_deleted(): void
    {
        $assignment = TripAssignment::factory()->create();

        $this->expectException(QueryException::class);
        $assignment->trip->delete();
    }
}
