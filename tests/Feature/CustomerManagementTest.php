<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string, string, bool}> */
    public static function endpoints(): array
    {
        return [
            'index' => ['GET', 'index', false], 'create' => ['GET', 'create', false],
            'store' => ['POST', 'store', false], 'edit' => ['GET', 'edit', true],
            'update' => ['PUT', 'update', true],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_guest_cannot_access_management(string $method, string $action, bool $target): void
    {
        $customer = Customer::factory()->create();

        $this->call($method, route('super-admin.customers.'.$action, $target ? $customer : []))
            ->assertRedirectToRoute('login');
    }

    #[DataProvider('endpoints')]
    public function test_tour_leader_cannot_access_management(string $method, string $action, bool $target): void
    {
        $customer = Customer::factory()->create();
        $leader = User::factory()->tourLeader()->create();

        $this->actingAs($leader)->call($method, route('super-admin.customers.'.$action, $target ? $customer : []), ['name' => 'Tampered'])
            ->assertForbidden();

        $this->assertSame($customer->name, $customer->fresh()->name);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_admin_lists_searches_and_renders_customer_forms(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = Customer::factory()->create(['name' => 'Sekolah Bandung']);
        Customer::factory()->create(['name' => 'Komunitas Jogja']);

        $this->actingAs($admin)->get(route('super-admin.customers.index', ['search' => 'Bandung']))
            ->assertOk()->assertSee('Sekolah Bandung')->assertDontSee('Komunitas Jogja');
        $this->get(route('super-admin.customers.create'))->assertOk()->assertSee('Tambah Customer');
        $this->get(route('super-admin.customers.edit', $customer))->assertOk()->assertSee('Sekolah Bandung');
    }

    public function test_admin_creates_customer_without_a_login_account(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('super-admin.customers.store'), [
            'name' => 'SMA Arfaka', 'pic_name' => 'Nadia', 'phone' => '+62 812 123',
            'notes' => 'Repeat customer',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('customers', ['name' => 'SMA Arfaka', 'pic_name' => 'Nadia', 'phone' => '+62 812 123', 'notes' => 'Repeat customer']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_customer_name_is_required_and_optional_fields_can_be_empty(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('super-admin.customers.store'), [])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('customers', 0);
        $this->post(route('super-admin.customers.store'), ['name' => 'Individu'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Individu', 'pic_name' => null, 'phone' => null, 'notes' => null]);
    }

    public function test_admin_edits_customer_and_output_is_escaped(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())->put(route('super-admin.customers.update', $customer), [
            'name' => '<script>alert(1)</script>', 'pic_name' => 'PIC Baru', 'phone' => '08123', 'notes' => 'Catatan baru',
        ])->assertRedirectToRoute('super-admin.customers.edit', $customer);

        $this->assertSame('PIC Baru', $customer->fresh()->pic_name);
        $this->get(route('super-admin.customers.index'))->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_customer_empty_state_and_no_delete_route(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('super-admin.customers.index'))
            ->assertSee('Belum ada customer.');

        $customer = Customer::factory()->create();
        $this->delete('/super-admin/customers/'.$customer->id)->assertMethodNotAllowed();
        $this->assertModelExists($customer);
    }
}
