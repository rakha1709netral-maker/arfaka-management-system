<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ARFAKA TOUR')
            ->assertSee('Login');
    }

    public function test_super_admin_can_login_and_is_redirected_to_super_admin_area(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute('super-admin.index');
        $this->assertAuthenticatedAs($user);
    }

    public function test_tour_leader_can_login_and_is_redirected_to_tour_leader_area(): void
    {
        $user = User::factory()->tourLeader()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute('tour-leader.index');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_an_incorrect_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    public function test_public_registration_route_is_not_available(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
    }

    public function test_super_admin_seeder_is_idempotent_and_hashes_the_password(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $this->seed(SuperAdminSeeder::class);

        $admin = User::query()->where('email', 'admin@arfaka.test')->sole();

        $this->assertSame('Administrator Arfaka', $admin->name);
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertSame(1, User::query()->where('email', 'admin@arfaka.test')->count());
    }
}
