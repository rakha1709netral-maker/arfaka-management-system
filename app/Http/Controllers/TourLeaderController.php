<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTourLeaderRequest;
use App\Http\Requests\UpdateTourLeaderRequest;
use App\Models\User;
use App\TripAssignmentStatus;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TourLeaderController extends Controller
{
    public function index(): View
    {
        $tourLeaders = User::query()
            ->where('role', UserRole::TourLeader)
            ->with(['tripAssignments' => fn ($assignments) => $assignments
                ->whereIn('status', [TripAssignmentStatus::Pending, TripAssignmentStatus::Accepted])
                ->with('trip.customer')
                ->latest('updated_at')
                ->latest('id')])
            ->latest()
            ->paginate(10);

        return view('super-admin.tour-leaders.index', ['tourLeaders' => $tourLeaders]);
    }

    public function create(): View
    {
        return view('super-admin.tour-leaders.create');
    }

    public function store(StoreTourLeaderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::TourLeader,
            'is_active' => true,
        ]);

        return redirect()
            ->route('super-admin.tour-leaders.index')
            ->with('status', 'Akun Tour Leader berhasil dibuat.');
    }

    public function edit(User $tourLeader): View
    {
        return view('super-admin.tour-leaders.edit', ['tourLeader' => $tourLeader]);
    }

    public function update(UpdateTourLeaderRequest $request, User $tourLeader): RedirectResponse
    {
        $tourLeader->update($request->validated());

        return redirect()
            ->route('super-admin.tour-leaders.edit', $tourLeader)
            ->with('status', 'Profil Tour Leader berhasil diperbarui.');
    }
}
