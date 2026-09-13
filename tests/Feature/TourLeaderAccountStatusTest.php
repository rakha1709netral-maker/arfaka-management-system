<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TourLeaderAccountStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_can_deactivate_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();

        $response = $this->actingAs($admin)->delete(
            route('super-admin.tour-leaders.activation.destroy', $tourLeader),
        );

        $response->assertRedirect();
        $this->assertFalse($tourLeader->fresh()->is_active);
    }

    public function test_inactive_tour_leader_cannot_login(): void
    {
        $tourLeader = User::factory()->tourLeader()->inactive()->create();

        $response = $this->post(route('login.store'), [
            'email' => $tourLeader->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Akun ini tidak aktif. Hubungi administrator.',
        ]);
        $this->assertGuest();
    }

    public function test_inactive_tour_leader_with_existing_session_is_logged_out_and_forbidden(): void
    {
        $tourLeader = User::factory()->tourLeader()->inactive()->create();

        $this->actingAs($tourLeader)
            ->get(route('tour-leader.index'))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_super_admin_can_reactivate_tour_leader_and_the_account_can_login(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->inactive()->create();

        $this->actingAs($admin)
            ->post(route('super-admin.tour-leaders.activation.store', $tourLeader))
            ->assertRedirect();

        $this->assertTrue($tourLeader->fresh()->is_active);

        $this->actingAsGuest()
            ->post(route('login.store'), [
                'email' => $tourLeader->email,
                'password' => 'password',
            ])
            ->assertRedirectToRoute('tour-leader.index');

        $this->assertAuthenticatedAs($tourLeader);
    }

    public function test_super_admin_can_change_tour_leader_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();

        $this->actingAs($admin)
            ->put(route('super-admin.tour-leaders.password.update', $tourLeader), [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('new-secure-password', $tourLeader->fresh()->password));

        $this->actingAsGuest()
            ->post(route('login.store'), [
                'email' => $tourLeader->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => $tourLeader->email,
            'password' => 'new-secure-password',
        ])->assertRedirectToRoute('tour-leader.index');

        $this->assertAuthenticatedAs($tourLeader);
    }

    public function test_invalid_password_update_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();
        $originalPassword = $tourLeader->password;

        $this->actingAs($admin)
            ->put(route('super-admin.tour-leaders.password.update', $tourLeader), [
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $tourLeader->fresh()->password);
    }
}
