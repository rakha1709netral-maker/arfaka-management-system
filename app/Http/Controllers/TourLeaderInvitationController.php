<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class TourLeaderInvitationController extends Controller
{
    public function edit(Request $request, string $token): View
    {
        $tourLeader = User::query()->where('email', $request->query('email'))->first();

        abort_unless($tourLeader?->role === UserRole::TourLeader
            && $tourLeader->is_active
            && $tourLeader->password_set_at === null
            && Password::broker()->tokenExists($tourLeader, $token), 404);

        return view('auth.accept-invitation', ['email' => $tourLeader->email, 'token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $tourLeader = User::query()->where('email', $validated['email'])->first();

        if ($tourLeader?->role !== UserRole::TourLeader || ! $tourLeader->is_active || $tourLeader->password_set_at !== null) {
            return back()->withErrors(['email' => 'Tautan undangan tidak valid atau telah kedaluwarsa.']);
        }

        $status = Password::broker()->reset($validated, function (User $account, string $password): void {
            $account->forceFill([
                'password' => Hash::make($password),
                'password_set_at' => now(),
            ])->setRememberToken(Str::random(60));
            $account->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Tautan undangan tidak valid atau telah kedaluwarsa.']);
        }

        return redirect()->route('login')->with('status', 'Password berhasil dibuat. Silakan login.');
    }
}
