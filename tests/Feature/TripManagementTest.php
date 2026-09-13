<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Trip;
use App\Models\User;
use App\TripStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, bool}> */
    public static function endpoints(): array
    {
        return [
            'index' => ['GET', 'index', false], 'create' => ['GET', 'create', false],
            'store' => ['POST', 'store', false], 'show' => ['GET', 'show', true],
            'edit' => ['GET', 'edit', true], 'update' => ['PUT', 'update', true],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_guest_cannot_access_admin_trips(string $method, string $action, bool $target): void
    {
        $trip = Trip::factory()->create();

        $this->call($method, route('super-admin.trips.'.$action, $target ? $trip : []))
            ->assertRedirectToRoute('login');
    }

    #[DataProvider('endpoints')]
    public function test_tour_leader_cannot_access_admin_trips(string $method, string $action, bool $target): void
    {
        $trip = Trip::factory()->create();

        $this->actingAs(User::factory()->tourLeader()->create())
            ->call($method, route('super-admin.trips.'.$action, $target ? $trip : []), ['name' => 'Tampered'])
            ->assertForbidden();

        $this->assertSame($trip->name, $trip->fresh()->name);
        $this->assertDatabaseCount('trips', 1);
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer): array
    {
        return [
            'name' => 'Trip Bandung', 'customer_id' => $customer->id, 'destination' => 'Bandung',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-03',
            'participant_count' => 30, 'status' => TripStatus::Draft->value, 'notes' => 'Bawa jaket',
        ];
    }

    public function test_admin_creates_trips_with_unique_server_generated_codes_and_customer_relation(): void
    {
        $customer = Customer::factory()->create();
        $payload = $this->payload($customer);
        $payload['code'] = 'UNTRUSTED-CODE';
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.trips.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('super-admin.trips.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

        $trips = Trip::query()->orderBy('id')->get();
        $this->assertCount(2, $trips);
        $this->assertNotSame($trips[0]->code, $trips[1]->code);
        $this->assertStringStartsWith('TRP-', $trips[0]->code);
        $this->assertNotSame('UNTRUSTED-CODE', $trips[0]->code);
        $this->assertTrue($trips[0]->customer->is($customer));
        $this->assertSame(30, $trips[0]->participant_count);
        $this->assertSame(TripStatus::Draft, $trips[0]->status);
        $this->assertDatabaseCount('trip_assignments', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidInput(): array
    {
        return [
            'name required' => ['name', ''], 'destination required' => ['destination', ''],
            'customer required' => ['customer_id', null], 'missing customer' => ['customer_id', 999999],
            'start required' => ['start_date', ''], 'invalid start' => ['start_date', 'not-a-date'],
            'end before start' => ['end_date', '2026-09-30'], 'end required' => ['end_date', ''],
            'zero participants' => ['participant_count', 0], 'negative participants' => ['participant_count', -1],
            'decimal participants' => ['participant_count', 1.5],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_trip_input_is_rejected(string $field, mixed $value): void
    {
        $payload = $this->payload(Customer::factory()->create());
        $payload[$field] = $value;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.trips.store'), $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('trip_assignments', 0);
    }

    public function test_admin_can_edit_trip_with_same_day_dates_and_code_is_unchanged(): void
    {
        $trip = Trip::factory()->create();
        $customer = Customer::factory()->create();
        $payload = $this->payload($customer);
        $payload['end_date'] = $payload['start_date'];
        $payload['code'] = 'CHANGED';
        $payload['status'] = TripStatus::Confirmed->value;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('super-admin.trips.update', $trip), $payload)
            ->assertRedirectToRoute('super-admin.trips.show', $trip);

        $saved = $trip->fresh();
        $this->assertSame($trip->code, $saved->code);
        $this->assertSame('Trip Bandung', $saved->name);
        $this->assertSame('Bandung', $saved->destination);
        $this->assertSame('Bawa jaket', $saved->notes);
        $this->assertSame('2026-10-01', $saved->end_date->format('Y-m-d'));
        $this->assertTrue($saved->customer->is($customer));
        $this->assertSame(TripStatus::Draft, $saved->status);
    }

    public function test_trip_status_is_system_managed_and_ignores_form_tampering(): void
    {
        $payload = $this->payload(Customer::factory()->create());
        $payload['status'] = TripStatus::Ongoing->value;

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('super-admin.trips.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(TripStatus::Draft, Trip::query()->sole()->status);
    }

    public function test_admin_can_render_all_trip_pages_and_inactive_users_are_not_selectable(): void
    {
        $trip = Trip::factory()->create();
        $active = User::factory()->tourLeader()->create(['name' => 'Active Guide']);
        $inactive = User::factory()->tourLeader()->inactive()->create(['name' => 'Inactive Guide']);
        $admin = User::factory()->superAdmin()->create(['name' => 'Admin Only']);

        $this->actingAs($admin)->get(route('super-admin.trips.index'))->assertOk()->assertSee($trip->code);
        $this->get(route('super-admin.trips.create'))->assertOk()->assertSee($active->name)
            ->assertDontSee($inactive->name)->assertDontSee($admin->name);
        $this->get(route('super-admin.trips.edit', $trip))->assertOk()->assertSee($trip->name);
        $this->get(route('super-admin.trips.show', $trip))->assertOk()->assertSee($trip->code);
    }

    public function test_trip_empty_state_and_no_hard_delete_route(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('super-admin.trips.index'))
            ->assertSee('Belum ada trip.');

        $trip = Trip::factory()->create();
        $this->delete('/super-admin/trips/'.$trip->id)->assertMethodNotAllowed();
        $this->assertModelExists($trip);
    }

    public function test_referenced_customer_cannot_be_deleted(): void
    {
        $trip = Trip::factory()->create();

        $this->expectException(QueryException::class);
        $trip->customer->delete();
    }

    public function test_duplicate_trip_codes_are_rejected_by_database(): void
    {
        $first = Trip::factory()->create();
        $second = Trip::factory()->create();

        $this->expectException(QueryException::class);
        $second->forceFill(['code' => $first->code])->save();
    }
}
