<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\TripAssignmentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssignedTripController extends Controller
{
    public function index(Request $request): View
    {
        $trips = $request->user()->assignedTrips()->with('customer')
            ->orderBy('start_date')->orderBy('trips.id')->paginate(10);
        $invitations = $request->user()->tripAssignments()
            ->where('status', TripAssignmentStatus::Pending)
            ->with('trip.customer')
            ->latest()
            ->get();
        $responses = $request->user()->tripAssignments()
            ->where('status', TripAssignmentStatus::Rejected)
            ->with('trip.customer')
            ->whereNotNull('responded_at')
            ->latest('responded_at')
            ->limit(5)
            ->get();

        return view('areas.tour-leader', compact('trips', 'invitations', 'responses'));
    }

    public function show(Trip $trip): View
    {
        Gate::authorize('view', $trip);
        $trip->load(['customer', 'tourLeaders']);

        return view('tour-leader.trips.show', compact('trip'));
    }
}
