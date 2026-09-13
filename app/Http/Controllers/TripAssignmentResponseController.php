<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripAssignment;
use App\TripAssignmentStatus;
use App\TripStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripAssignmentResponseController extends Controller
{
    public function store(Request $request, TripAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->user_id === $request->user()->id, 404);

        $userId = $request->user()->id;
        DB::transaction(function () use ($assignment, $userId): void {
            $assignment = TripAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->user_id === $userId, 404);
            $trip = Trip::query()->lockForUpdate()->findOrFail($assignment->trip_id);
            if ($assignment->status !== TripAssignmentStatus::Pending) {
                throw ValidationException::withMessages(['assignment' => 'Undangan ini sudah direspons.']);
            }
            if ($trip->status !== TripStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'assignment' => 'Trip belum dapat dimulai karena DP customer belum tercatat.',
                ]);
            }

            $assignment->update([
                'status' => TripAssignmentStatus::Accepted,
                'response_note' => null,
                'responded_at' => now(),
            ]);
            $trip->update(['status' => TripStatus::Ongoing]);
        });

        return back()->with('status', 'Tugas diterima. Trip sekarang berstatus ongoing dan pencatatan pengeluaran terbuka.');
    }

    public function destroy(Request $request, TripAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->user_id === $request->user()->id, 404);
        $data = $request->validate(['response_note' => ['required', 'string', 'max:1000']]);

        $userId = $request->user()->id;
        DB::transaction(function () use ($assignment, $data, $userId): void {
            $assignment = TripAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->user_id === $userId, 404);
            if ($assignment->status !== TripAssignmentStatus::Pending) {
                throw ValidationException::withMessages(['assignment' => 'Undangan ini sudah direspons.']);
            }

            $assignment->update([
                'status' => TripAssignmentStatus::Rejected,
                'response_note' => $data['response_note'],
                'responded_at' => now(),
            ]);
        });

        return back()->with('status', 'Tugas ditolak. Admin dapat melihat alasan dan memilih TL lain.');
    }
}
