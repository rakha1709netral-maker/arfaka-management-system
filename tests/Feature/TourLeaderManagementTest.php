<?php

namespace Tests\Feature;

use App\Models\TripAssignment;
use App\Models\User;
use App\Notifications\TourLeaderInvitationNotification;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TourLeaderManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login_from_tour_leader_management(): void
    {
        $this->get(route('super-admin.tour-leaders.index'))
            ->assertRedirectToRoute('login');
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('tourLeaderManagementRoutes')]
    public function test_tour_leader_is_forbidden_from_management_routes(
        string $method,
        string $routeName,
        bool $requiresTarget,
        array $payload,
    ): void {
        $actor = User::factory()->tourLeader()->create();
        $target = User::factory()->tourLeader()->create();
        $url = route($routeName, $requiresTarget ? $target : []);

        $this->actingAs($actor)
            ->call($method, $url, $payload)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{string, string, bool, array<string, string>}>
     */
    public static function tourLeaderManagementRoutes(): array
    {
        return [
            'list' => ['GET', 'super-admin.tour-leaders.index', false, []],
            'create form' => ['GET', 'super-admin.tour-leaders.create', false, []],
            'store' => ['POST', 'super-admin.tour-leaders.store', false, [
                'name' => 'Blocked User',
                'email' => 'blocked@arfaka.test',
            ]],
            'edit form' => ['GET', 'super-admin.tour-leaders.edit', true, []],
            'update' => ['PUT', 'super-admin.tour-leaders.update', true, [
                'name' => 'Blocked Update',
                'email' => 'blocked-update@arfaka.test',
            ]],
            'activate' => ['POST', 'super-admin.tour-leaders.activation.store', true, []],
            'deactivate' => ['DELETE', 'super-admin.tour-leaders.activation.destroy', true, []],
            'invitation' => ['POST', 'super-admin.tour-leaders.invitation.store', true, []],
            'password reset' => ['POST', 'super-admin.tour-leaders.password-reset.store', true, []],
        ];
    }

    public function test_super_admin_can_view_only_tour_leaders(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();

        $this->actingAs($admin)
            ->get(route('super-admin.tour-leaders.index'))
            ->assertOk()
            ->assertSee($tourLeader->name)
            ->assertSee($tourLeader->email)
            ->assertDontSee($admin->email);
    }

    public function test_tour_leader_list_has_an_empty_state(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('super-admin.tour-leaders.index'))
            ->assertOk()
            ->assertSee('Belum ada akun Tour Leader');
    }

    public function test_super_admin_can_see_current_trip_assignments_for_each_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();
        $accepted = TripAssignment::factory()->for($tourLeader, 'user')->create();
        $pending = TripAssignment::factory()->for($tourLeader, 'user')->pending()->create();
        TripAssignment::factory()->create();

        $this->actingAs($admin)->get(route('super-admin.tour-leaders.index'))
            ->assertSee('Penugasan trip')
            ->assertSee($accepted->trip->name)
            ->assertSee($accepted->trip->code)
            ->assertSee($pending->trip->name)
            ->assertSee('Diterima')
            ->assertSee('Menunggu persetujuan')
            ->assertViewHas('tourLeaders', function ($tourLeaders) use ($tourLeader, $accepted, $pending): bool {
                $listedLeader = $tourLeaders->firstWhere('id', $tourLeader->id);

                return $listedLeader !== null
                    && $listedLeader->tripAssignments->pluck('trip_id')->sort()->values()->all()
                        === collect([$accepted->trip_id, $pending->trip_id])->sort()->values()->all();
            });
    }

    public function test_super_admin_can_create_an_active_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Notification::fake();

        $response = $this->actingAs($admin)->post(route('super-admin.tour-leaders.store'), [
            'name' => 'Nadia Guide',
            'email' => 'nadia@arfaka.test',
            'role' => UserRole::SuperAdmin->value,
            'is_active' => false,
        ]);

        $response->assertRedirectToRoute('super-admin.tour-leaders.index');

        $tourLeader = User::query()->where('email', 'nadia@arfaka.test')->sole();

        $this->assertSame('Nadia Guide', $tourLeader->name);
        $this->assertSame(UserRole::TourLeader, $tourLeader->role);
        $this->assertTrue($tourLeader->is_active);
        $this->assertNull($tourLeader->password);
        $this->assertNull($tourLeader->password_set_at);
        Notification::assertSentTo($tourLeader, TourLeaderInvitationNotification::class);
    }

    public function test_duplicate_email_is_rejected_when_creating_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $existing = User::factory()->tourLeader()->create();

        $response = $this->actingAs($admin)->post(route('super-admin.tour-leaders.store'), [
            'name' => 'Duplicate Email',
            'email' => $existing->email,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }

    public function test_invalid_input_is_rejected_when_creating_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('super-admin.tour-leaders.store'), [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['name', 'email']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_super_admin_can_update_tour_leader_profile_without_changing_role_or_status(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();

        $response = $this->actingAs($admin)->put(
            route('super-admin.tour-leaders.update', $tourLeader),
            [
                'name' => 'Updated Leader',
                'email' => 'updated@arfaka.test',
                'role' => UserRole::SuperAdmin->value,
                'is_active' => false,
            ],
        );

        $response->assertRedirectToRoute('super-admin.tour-leaders.edit', $tourLeader);

        $tourLeader->refresh();

        $this->assertSame('Updated Leader', $tourLeader->name);
        $this->assertSame('updated@arfaka.test', $tourLeader->email);
        $this->assertSame(UserRole::TourLeader, $tourLeader->role);
        $this->assertTrue($tourLeader->is_active);
    }

    public function test_tour_leader_can_keep_the_same_email_when_updating_profile(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();

        $this->actingAs($admin)
            ->put(route('super-admin.tour-leaders.update', $tourLeader), [
                'name' => 'Updated Name',
                'email' => $tourLeader->email,
            ])
            ->assertRedirectToRoute('super-admin.tour-leaders.edit', $tourLeader);

        $this->assertSame('Updated Name', $tourLeader->fresh()->name);
    }

    public function test_duplicate_email_is_rejected_when_updating_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $firstTourLeader = User::factory()->tourLeader()->create();
        $secondTourLeader = User::factory()->tourLeader()->create();

        $response = $this->actingAs($admin)->put(
            route('super-admin.tour-leaders.update', $firstTourLeader),
            [
                'name' => 'Duplicate Email',
                'email' => $secondTourLeader->email,
            ],
        );

        $response->assertSessionHasErrors('email');
        $this->assertSame($firstTourLeader->email, $firstTourLeader->fresh()->email);
    }

    public function test_non_tour_leader_cannot_be_targeted_by_management_routes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('super-admin.tour-leaders.edit', $otherAdmin))
            ->assertNotFound();
    }
}
