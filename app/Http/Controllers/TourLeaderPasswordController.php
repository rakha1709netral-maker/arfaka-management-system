<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTourLeaderPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TourLeaderPasswordController extends Controller
{
    public function update(UpdateTourLeaderPasswordRequest $request, User $tourLeader): RedirectResponse
    {
        $tourLeader->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->setRememberToken(Str::random(60));
        $tourLeader->save();

        return back()->with('status', 'Password Tour Leader berhasil diperbarui.');
    }
}
