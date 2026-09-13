<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTripRequest;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\User;
use App\TripAssignmentStatus;
use App\TripStatus;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripController extends Controller
{
    public function index(): View
    {
        $trips = Trip::query()->with(['customer', 'tourLeaders'])
            ->orderByDesc('start_date')->orderByDesc('id')->paginate(10);

        return view('super-admin.trips.index', compact('trips'));
    }

    public function create(): View
    {
        return $this->form(new Trip(['status' => TripStatus::Draft]));
    }

    public function store(SaveTripRequest $request): RedirectResponse
    {
        $trip = $this->saveTrip($request, new Trip);

        return redirect()->route('super-admin.trips.show', $trip)
            ->with('status', 'Trip berhasil dibuat.');
    }

    public function show(Trip $trip): View
    {
        Gate::authorize('view', $trip);
        $trip->load(['customer', 'tourLeaders']);

        return view('super-admin.trips.show', compact('trip'));
    }

    public function edit(Trip $trip): View
    {
        return $this->form($trip);
    }

    public function update(SaveTripRequest $request, Trip $trip): RedirectResponse
    {
        $trip = $this->saveTrip($request, $trip);

        return redirect()->route('super-admin.trips.show', $trip)
            ->with('status', 'Trip berhasil diperbarui.');
    }

    private function form(Trip $trip): View
    {
        $trip->load('tourLeaders');
        $customers = Customer::query()->orderBy('name')->orderBy('id')->get(['id', 'name']);
        $tourLeaders = User::query()->where('role', UserRole::TourLeader)->where('is_active', true)
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);

        return view('super-admin.trips.form', compact('trip', 'customers', 'tourLeaders'));
    }

    private function saveTrip(SaveTripRequest $request, Trip $trip): Trip
    {
        return DB::transaction(function () use ($request, $trip): Trip {
            if ($trip->exists) {
                $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
                if ($trip->finances_locked_at !== null) {
                    throw ValidationException::withMessages(['status' => 'Trip dikunci oleh settlement. Buka kembali dari halaman keuangan.']);
                }
                if ((int) $request->validated('customer_id') !== $trip->customer_id && ($trip->contract_amount !== null || $trip->financeEntries()->exists())) {
                    throw ValidationException::withMessages(['customer_id' => 'Customer tidak dapat diganti setelah keuangan trip mulai dicatat.']);
                }
            }

            $leaderId = $request->validated('tour_leader_id');
            if ($leaderId !== null) {
                $leader = User::query()->where('role', UserRole::TourLeader)->where('is_active', true)
                    ->lockForUpdate()->find($leaderId);

                if ($leader === null) {
                    throw ValidationException::withMessages([
                        'tour_leader_id' => 'Pilih Tour Leader yang masih aktif.',
                    ]);
                }
            }

            $trip->fill($request->safe()->except('tour_leader_id'));
            if (! $trip->exists) {
                $trip->status = TripStatus::Draft;
            }
            $trip->save();

            $assignment = $trip->assignments()->lockForUpdate()->first();
            if ($leaderId === null) {
                $assignment?->delete();
                if ($trip->status === TripStatus::Ongoing) {
                    $trip->update(['status' => TripStatus::Confirmed]);
                }
            } elseif ($assignment === null) {
                $trip->assignments()->create([
                    'user_id' => $leaderId,
                    'status' => TripAssignmentStatus::Pending,
                ]);
            } elseif ($assignment->user_id !== $leaderId || $assignment->status === TripAssignmentStatus::Rejected) {
                $assignment->update([
                    'user_id' => $leaderId,
                    'status' => TripAssignmentStatus::Pending,
                    'response_note' => null,
                    'responded_at' => null,
                ]);
                if ($trip->status === TripStatus::Ongoing) {
                    $trip->update(['status' => TripStatus::Confirmed]);
                }
            }

            return $trip;
        });
    }
}
