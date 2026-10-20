<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user?->is_active && $user->password_set_at !== null) {
            try {
                Password::broker()->sendResetLink(['email' => $user->email]);
            } catch (Throwable $exception) {
                Password::broker()->deleteToken($user);
                Log::warning('Password reset mail delivery failed.', ['exception' => $exception::class]);
            }
        }

        return back()->with('status', 'Jika email terdaftar dan dapat menggunakan sistem, link reset password akan dikirim.');
    }
}
