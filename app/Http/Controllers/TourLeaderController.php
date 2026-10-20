<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTourLeaderRequest;
use App\Http\Requests\UpdateTourLeaderRequest;
use App\Models\User;
use App\Notifications\TourLeaderInvitationNotification;
use App\TripAssignmentStatus;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

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

        $tourLeader = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => null,
            'password_set_at' => null,
            'role' => UserRole::TourLeader,
            'is_active' => true,
        ]);

        try {
            $token = Password::broker()->createToken($tourLeader);
            $tourLeader->notify(new TourLeaderInvitationNotification($token));
        } catch (Throwable $exception) {
            Password::broker()->deleteToken($tourLeader);
            Log::warning('Tour Leader invitation delivery failed.', ['exception' => $exception::class]);

            return redirect()
                ->route('super-admin.tour-leaders.index')
                ->with('error', 'Akun berhasil dibuat, tetapi email undangan belum berhasil dikirim. Silakan kirim ulang undangan.');
        }

        return redirect()
            ->route('super-admin.tour-leaders.index')
            ->with('status', 'Akun Tour Leader berhasil dibuat. Undangan membuat password telah dikirim.');
    }

    public function edit(User $tourLeader): View
    {
        return view('super-admin.tour-leaders.edit', ['tourLeader' => $tourLeader]);
    }

    public function update(UpdateTourLeaderRequest $request, User $tourLeader): RedirectResponse
    {
        if ($tourLeader->email !== $request->validated('email')) {
            Password::broker()->deleteToken($tourLeader);
        }

        $tourLeader->update($request->validated());

        return redirect()
            ->route('super-admin.tour-leaders.edit', $tourLeader)
            ->with('status', 'Profil Tour Leader berhasil diperbarui.');
    }
}
