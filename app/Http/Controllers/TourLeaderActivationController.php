<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TourLeaderActivationController extends Controller
{
    public function store(User $tourLeader): RedirectResponse
    {
        $tourLeader->update(['is_active' => true]);

        return back()->with('status', 'Akun Tour Leader berhasil diaktifkan.');
    }

    public function destroy(User $tourLeader): RedirectResponse
    {
        $tourLeader->update(['is_active' => false]);

        return back()->with('status', 'Akun Tour Leader berhasil dinonaktifkan.');
    }
}
