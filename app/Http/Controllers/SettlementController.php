<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\TripFinance;
use App\TripStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementController extends Controller
{
    public function store(Request $request, Trip $trip, TripFinance $finance): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($request, $data, $finance): void {
            $summary = $finance->summary($locked);
            $unsettled = array_filter($summary['balances'], fn (int $balance): bool => $balance !== 0);
            if ($locked->contract_amount === null || $summary['receivable'] !== 0 || $summary['pending_count'] > 0 || $unsettled !== []) {
                throw ValidationException::withMessages(['finance' => 'Belum dapat ditutup: isi kontrak, lunasi tagihan, periksa seluruh pengeluaran, dan selesaikan setiap saldo TL.']);
            }
            $locked->finances_locked_at = now();
            $locked->status = TripStatus::Closed;
            $locked->save();
            $finance->audit($locked, $request->user(), 'settlement.closed', $summary, $data['reason']);
        });

        return back()->with('status', 'Settlement selesai. Data trip dan keuangan dikunci.');
    }

    public function destroy(Request $request, Trip $trip, TripFinance $finance): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($trip, $request, $data, $finance): void {
            $locked = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            if ($locked->finances_locked_at === null) {
                throw ValidationException::withMessages(['finance' => 'Keuangan trip belum dikunci.']);
            }
            $locked->finances_locked_at = null;
            $locked->status = TripStatus::AwaitingSettlement;
            $locked->save();
            $finance->audit($locked, $request->user(), 'settlement.reopened', [], $data['reason']);
        });

        return back()->with('status', 'Settlement dibuka kembali. Alasan tersimpan di histori.');
    }
}
