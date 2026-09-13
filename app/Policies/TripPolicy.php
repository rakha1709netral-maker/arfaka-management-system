<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;
use App\TripAssignmentStatus;
use App\UserRole;
use Illuminate\Auth\Access\Response;

class TripPolicy
{
    public function view(User $user, Trip $trip): Response
    {
        if (! $user->is_active) {
            return Response::denyAsNotFound();
        }

        if ($user->role === UserRole::SuperAdmin) {
            return Response::allow();
        }

        return $user->role === UserRole::TourLeader
            && $trip->assignments()
                ->where('user_id', $user->id)
                ->where('status', TripAssignmentStatus::Accepted)
                ->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
