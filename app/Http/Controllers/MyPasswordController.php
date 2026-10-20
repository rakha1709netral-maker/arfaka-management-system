<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MyPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.my-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->setRememberToken(Str::random(60));
        $request->user()->save();

        return back()->with('status', 'Password akun Anda berhasil diperbarui.');
    }
}
