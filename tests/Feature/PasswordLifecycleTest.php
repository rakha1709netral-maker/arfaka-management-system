<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ArfakaPasswordResetNotification;
use App\Notifications\TourLeaderInvitationNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class PasswordLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_create_a_tour_leader(): void
    {
        $this->post(route('super-admin.tour-leaders.store'), [
            'name' => 'Nadia', 'email' => 'nadia@arfaka.test',
        ])->assertRedirectToRoute('login');

        $this->assertDatabaseMissing('users', ['email' => 'nadia@arfaka.test']);
    }

    public function test_create_keeps_pending_account_when_invitation_mail_fails(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->mock(Dispatcher::class)
            ->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $this->actingAs($admin)->post(route('super-admin.tour-leaders.store'), [
            'name' => 'Nadia', 'email' => 'nadia@arfaka.test',
        ])->assertSessionHas('error', 'Akun berhasil dibuat, tetapi email undangan belum berhasil dikirim. Silakan kirim ulang undangan.');

        $tourLeader = User::query()->where('email', 'nadia@arfaka.test')->sole();
        $this->assertNull($tourLeader->password);
        $this->assertNull($tourLeader->password_set_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $tourLeader->email]);
    }

    public function test_pending_tour_leader_cannot_login_before_setting_password(): void
    {
        $tourLeader = User::factory()->pendingInvitation()->create();

        $this->post(route('login.store'), [
            'email' => $tourLeader->email, 'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_valid_invitation_sets_hashed_password_and_cannot_be_reused(): void
    {
        $tourLeader = User::factory()->pendingInvitation()->create();
        $token = Password::broker()->createToken($tourLeader);

        $this->get(route('invitation.edit', ['token' => $token, 'email' => $tourLeader->email]))
            ->assertSee('Buat Password');

        $payload = [
            'token' => $token, 'email' => $tourLeader->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];
        $this->post(route('invitation.update'), $payload)->assertRedirectToRoute('login');

        $tourLeader->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $tourLeader->password));
        $this->assertNotNull($tourLeader->password_set_at);
        $this->assertFalse(Password::broker()->tokenExists($tourLeader, $token));

        $this->post(route('invitation.update'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login.store'), [
            'email' => $tourLeader->email, 'password' => 'new-secure-password',
        ])->assertRedirectToRoute('tour-leader.index');
    }

    public function test_expired_or_inactive_invitation_is_rejected(): void
    {
        $this->travelTo('2026-10-01 09:00:00');
        $tourLeader = User::factory()->pendingInvitation()->create();
        $token = Password::broker()->createToken($tourLeader);
        $this->travel(61)->minutes();

        $this->get(route('invitation.edit', ['token' => $token, 'email' => $tourLeader->email]))
            ->assertNotFound();
        $this->post(route('invitation.update'), [
            'token' => $token, 'email' => $tourLeader->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertNull($tourLeader->fresh()->password);

        $this->travelBack();
        $freshToken = Password::broker()->createToken($tourLeader);
        $tourLeader->update(['is_active' => false]);
        $this->get(route('invitation.edit', ['token' => $freshToken, 'email' => $tourLeader->email]))
            ->assertNotFound();
        $this->post(route('invitation.update'), [
            'token' => $freshToken, 'email' => $tourLeader->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_resending_invitation_invalidates_old_token(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->pendingInvitation()->create();
        $oldToken = Password::broker()->createToken($tourLeader);
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('super-admin.tour-leaders.invitation.store', $tourLeader))
            ->assertSessionHas('status');

        Notification::assertSentTo($tourLeader, TourLeaderInvitationNotification::class);
        $this->assertFalse(Password::broker()->tokenExists($tourLeader, $oldToken));
    }

    public function test_deactivation_revokes_outstanding_account_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->pendingInvitation()->create();
        $token = Password::broker()->createToken($tourLeader);

        $this->actingAs($admin)
            ->delete(route('super-admin.tour-leaders.activation.destroy', $tourLeader))
            ->assertRedirect();

        $this->assertFalse(Password::broker()->tokenExists($tourLeader, $token));
    }

    public function test_ineligible_tour_leaders_cannot_receive_invitation_again(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $onboarded = User::factory()->create();
        $inactive = User::factory()->pendingInvitation()->inactive()->create();
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('super-admin.tour-leaders.invitation.store', $onboarded))
            ->assertSessionHas('error');
        $this->post(route('super-admin.tour-leaders.invitation.store', $inactive))
            ->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_has_generic_response_for_unknown_email(): void
    {
        $this->get(route('password.request'))->assertSee('Lupa Password');

        $this->post(route('password.email'), ['email' => 'unknown@arfaka.test'])
            ->assertSessionHas('status', 'Jika email terdaftar dan dapat menggunakan sistem, link reset password akan dikirim.');
    }

    public function test_both_roles_receive_reset_link_using_configured_app_url(): void
    {
        config()->set('app.url', 'https://sistem.arfaka.test');
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->tourLeader()->create();
        Notification::fake();

        foreach ([$admin, $tourLeader] as $user) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHas('status');
            Notification::assertSentTo($user, ArfakaPasswordResetNotification::class, function ($notification) use ($user): bool {
                return str_starts_with($notification->toMail($user)->actionUrl, 'https://sistem.arfaka.test/reset-password/');
            });
        }
    }

    public function test_invitation_link_uses_configured_app_url(): void
    {
        config()->set('app.url', 'https://sistem.arfaka.test');
        $admin = User::factory()->superAdmin()->create();
        Notification::fake();

        $this->actingAs($admin)->post(route('super-admin.tour-leaders.store'), [
            'name' => 'Nadia', 'email' => 'nadia@arfaka.test',
        ])->assertSessionHas('status');

        $tourLeader = User::query()->where('email', 'nadia@arfaka.test')->sole();
        Notification::assertSentTo($tourLeader, TourLeaderInvitationNotification::class, function ($notification) use ($tourLeader): bool {
            return str_starts_with($notification->toMail($tourLeader)->actionUrl, 'https://sistem.arfaka.test/invitation/');
        });
    }

    public function test_inactive_or_pending_user_receives_no_forgot_password_email(): void
    {
        $inactive = User::factory()->inactive()->create();
        $pending = User::factory()->pendingInvitation()->create();
        Notification::fake();

        foreach ([$inactive, $pending] as $user) {
            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHas('status', 'Jika email terdaftar dan dapat menggunakan sistem, link reset password akan dikirim.');
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        }

        Notification::assertNothingSent();
    }

    public function test_valid_reset_changes_password_invalidates_sessions_and_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'old-session', 'user_id' => $user->id,
            'payload' => 'old', 'last_activity' => now()->timestamp,
        ]);
        config()->set('session.driver', 'database');

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSee('Reset Password');
        $payload = [
            'token' => $token, 'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];
        $this->post(route('password.update'), $payload)->assertRedirectToRoute('login');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertFalse(Password::broker()->tokenExists($user, $token));

        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-secure-password'])
            ->assertRedirectToRoute('tour-leader.index');
    }

    public function test_invalid_expired_or_inactive_reset_token_is_rejected(): void
    {
        $this->travelTo('2026-10-01 09:00:00');
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => 'invalid', 'email' => $user->email]))
            ->assertNotFound();
        $this->post(route('password.update'), [
            'token' => 'invalid', 'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->travelBack();
        $freshToken = Password::broker()->createToken($user);
        $user->update(['is_active' => false]);
        $this->get(route('password.reset', ['token' => $freshToken, 'email' => $user->email]))
            ->assertNotFound();
        $this->post(route('password.update'), [
            'token' => $freshToken, 'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_admin_reset_link_requires_active_onboarded_tour_leader(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->create();
        $pending = User::factory()->pendingInvitation()->create();
        $inactive = User::factory()->inactive()->create();
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('super-admin.tour-leaders.password-reset.store', $tourLeader))
            ->assertSessionHas('status');
        Notification::assertSentTo($tourLeader, ArfakaPasswordResetNotification::class);

        $this->post(route('super-admin.tour-leaders.password-reset.store', $pending))
            ->assertSessionHas('error');
        $this->post(route('super-admin.tour-leaders.password-reset.store', $inactive))
            ->assertSessionHas('error');
        Notification::assertNotSentTo($pending, ArfakaPasswordResetNotification::class);
        Notification::assertNotSentTo($inactive, ArfakaPasswordResetNotification::class);
    }

    public function test_tour_leader_list_shows_onboarding_states_and_relevant_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->pendingInvitation()->create(['name' => 'Pending Leader']);
        User::factory()->create(['name' => 'Ready Leader']);
        User::factory()->inactive()->create(['name' => 'Inactive Leader']);

        $this->actingAs($admin)->get(route('super-admin.tour-leaders.index'))
            ->assertSee('Menunggu Aktivasi Akun')
            ->assertSee('Aktif')
            ->assertSee('Nonaktif')
            ->assertSee('Kirim Ulang Undangan')
            ->assertSee('Kirim Link Reset Password');
    }

    public function test_admin_account_links_are_rate_limited(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->pendingInvitation()->create();
        Notification::fake();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)
                ->post(route('super-admin.tour-leaders.invitation.store', $tourLeader))
                ->assertRedirect();
        }

        $this->post(route('super-admin.tour-leaders.invitation.store', $tourLeader))
            ->assertTooManyRequests();
    }

    public function test_admin_password_reset_links_are_rate_limited(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tourLeader = User::factory()->create();
        Notification::fake();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)
                ->post(route('super-admin.tour-leaders.password-reset.store', $tourLeader))
                ->assertRedirect();
        }

        $this->post(route('super-admin.tour-leaders.password-reset.store', $tourLeader))
            ->assertTooManyRequests();
    }

    public function test_forgot_password_requests_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'unknown@arfaka.test'])
                ->assertSessionHas('status');
        }

        $this->post(route('password.email'), ['email' => 'unknown@arfaka.test'])
            ->assertTooManyRequests();
    }

    public function test_both_roles_can_change_their_own_password(): void
    {
        foreach ([User::factory()->superAdmin()->create(), User::factory()->tourLeader()->create()] as $user) {
            $this->actingAs($user)->get(route('my-password.edit'))->assertSee('Ubah Password');
            $this->put(route('my-password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHas('status');

            $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));

            $this->actingAsGuest()->post(route('login.store'), [
                'email' => $user->email, 'password' => 'password',
            ])->assertSessionHasErrors('email');
            $this->post(route('login.store'), [
                'email' => $user->email, 'password' => 'new-secure-password',
            ])->assertRedirectToRoute($user->role->homeRouteName());
            $this->post(route('logout'))->assertRedirectToRoute('login');
        }
    }

    public function test_change_password_requires_current_password_and_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('my-password.update'), [
            'current_password' => 'incorrect',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('my-password.update'), [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_inactive_account_cannot_open_my_password_page(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->get(route('my-password.edit'))
            ->assertForbidden();
    }
}
