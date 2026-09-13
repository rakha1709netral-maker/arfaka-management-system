<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_protected_area(): void
    {
        $this->get(route('super-admin.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_super_admin_can_open_super_admin_area(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('super-admin.index'))
            ->assertOk()
            ->assertSee('Super Admin Area');
    }

    public function test_tour_leader_is_forbidden_from_super_admin_area(): void
    {
        $user = User::factory()->tourLeader()->create();

        $this->actingAs($user)
            ->get(route('super-admin.index'))
            ->assertForbidden();
    }

    public function test_tour_leader_can_open_tour_leader_area(): void
    {
        $user = User::factory()->tourLeader()->create();

        $this->actingAs($user)
            ->get(route('tour-leader.index'))
            ->assertOk()
            ->assertSee('Tour Leader Area');
    }

    public function test_super_admin_is_forbidden_from_tour_leader_area(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('tour-leader.index'))
            ->assertForbidden();
    }
}
