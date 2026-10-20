<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function edit(Request $request, string $token): View
    {
        $user = User::query()->where('email', $request->query('email'))->first();

        abort_unless($user?->is_active && $user->password_set_at !== null && Password::broker()->tokenExists($user, $token), 404);

        return view('auth.reset-password', ['email' => $user->email, 'token' => $token]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user?->is_active || $user->password_set_at === null) {
            return back()->withErrors(['email' => 'Tautan reset password tidak valid atau telah kedaluwarsa.']);
        }

        $status = Password::broker()->reset($validated, function (User $account, string $password): void {
            $account->forceFill(['password' => Hash::make($password)])
                ->setRememberToken(Str::random(60));
            $account->save();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $account->getKey())
                    ->delete();
            }

            event(new PasswordReset($account));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Tautan reset password tidak valid atau telah kedaluwarsa.']);
        }

        return redirect()->route('login')->with('status', 'Password berhasil diperbarui. Silakan login.');
    }
}
