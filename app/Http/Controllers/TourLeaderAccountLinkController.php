<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\TourLeaderInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class TourLeaderAccountLinkController extends Controller
{
    public function invite(User $tourLeader): RedirectResponse
    {
        if (! $tourLeader->is_active || $tourLeader->password_set_at !== null) {
            return back()->with('error', 'Undangan hanya dapat dikirim ke akun aktif yang belum membuat password.');
        }

        try {
            $token = Password::broker()->createToken($tourLeader);
            $tourLeader->notify(new TourLeaderInvitationNotification($token));
        } catch (Throwable $exception) {
            Password::broker()->deleteToken($tourLeader);
            Log::warning('Tour Leader invitation delivery failed.', ['exception' => $exception::class]);

            return back()->with('error', 'Email undangan belum berhasil dikirim. Silakan coba lagi.');
        }

        return back()->with('status', 'Undangan membuat password telah dikirim ulang.');
    }

    public function reset(User $tourLeader): RedirectResponse
    {
        if (! $tourLeader->is_active || $tourLeader->password_set_at === null) {
            return back()->with('error', 'Link reset hanya dapat dikirim ke akun aktif yang sudah membuat password.');
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $tourLeader->email]);
        } catch (Throwable $exception) {
            Password::broker()->deleteToken($tourLeader);
            Log::warning('Tour Leader password reset delivery failed.', ['exception' => $exception::class]);

            return back()->with('error', 'Email reset password belum berhasil dikirim. Silakan coba lagi.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'Tunggu sebentar sebelum mengirim ulang link reset password.');
        }

        return back()->with('status', 'Link reset password telah dikirim ke Tour Leader.');
    }
}
