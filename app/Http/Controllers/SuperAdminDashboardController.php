<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\TripAssignment;
use App\Models\User;
use App\TripAssignmentStatus;
use App\TripStatus;
use App\UserRole;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function index(): View
    {
        $metrics = [
            'ongoing' => Trip::query()->where('status', TripStatus::Ongoing)->count(),
            'confirmed' => Trip::query()->where('status', TripStatus::Confirmed)->count(),
            'draft' => Trip::query()->where('status', TripStatus::Draft)->count(),
            'pending_expenses' => FinanceEntry::query()->where('status', 'pending')->count(),
            'pending_assignments' => TripAssignment::query()->where('status', TripAssignmentStatus::Pending)->count(),
            'customers' => Customer::query()->count(),
            'active_leaders' => User::query()->where('role', UserRole::TourLeader)->where('is_active', true)->count(),
        ];
        $upcomingTrips = Trip::query()
            ->with(['customer:id,name', 'tourLeaders:id,name'])
            ->whereIn('status', [TripStatus::Confirmed, TripStatus::Ongoing])
            ->orderBy('start_date')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return view('areas.super-admin', compact('metrics', 'upcomingTrips'));
    }
}
