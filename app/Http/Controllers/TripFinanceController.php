<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use App\FinanceType;
use App\Models\Trip;
use App\Models\User;
use App\TripFinance;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TripFinanceController extends Controller
{
    public function show(Request $request, Trip $trip, TripFinance $finance): View
    {
        Gate::authorize('view', $trip);
        $isAdmin = $request->user()->role === UserRole::SuperAdmin;
        $leaderId = $isAdmin ? null : $request->user()->id;
        $summary = $finance->summary($trip, $leaderId);
        $trip->load('customer');
        $entries = $trip->financeEntries()->with(['creator:id,name', 'leader:id,name', 'reviewer:id,name'])
            ->when(! $isAdmin, fn ($query) => $query->where('leader_id', $leaderId))
            ->orderByDesc('occurred_on')->orderByDesc('id')->paginate(15)->withQueryString();
        $leaders = $isAdmin ? User::query()->where('role', UserRole::TourLeader)
            ->where(function ($query) use ($trip): void {
                $query->whereHas('tripAssignments', fn ($assignments) => $assignments->where('trip_id', $trip->id))
                    ->orWhereIn('id', $trip->financeEntries()->whereNotNull('leader_id')->select('leader_id'));
            })->orderBy('name')->get(['id', 'name', 'is_active']) : collect();
        $budgets = $isAdmin ? $trip->budgetItems()->orderBy('id')->get() : collect();
        $audits = $isAdmin ? $trip->financeAudits()->with('user:id,name')->orderByDesc('id')->limit(30)->get() : collect();

        return view('finance.trip', [
            'trip' => $trip, 'summary' => $summary, 'entries' => $entries, 'leaders' => $leaders,
            'budgets' => $budgets, 'audits' => $audits, 'isAdmin' => $isAdmin,
            'categories' => ExpenseCategory::cases(), 'types' => $isAdmin ? FinanceType::cases() : [FinanceType::Expense],
        ]);
    }

    public function update(Request $request, Trip $trip, TripFinance $finance): RedirectResponse
    {
        $data = $request->validate([
            'contract_amount' => ['required', 'integer', 'min:0', 'max:'.TripFinance::MAX_AMOUNT],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($request, $data, $finance): void {
            if ((int) $data['contract_amount'] < $finance->summary($locked)['payment']) {
                throw ValidationException::withMessages(['contract_amount' => 'Nilai kontrak tidak boleh di bawah pembayaran yang sudah diterima.']);
            }
            $previous = $locked->contract_amount;
            $locked->contract_amount = $data['contract_amount'];
            $locked->save();
            $finance->audit($locked, $request->user(), 'contract.updated', ['previous' => $previous, 'amount' => $locked->contract_amount], $data['reason']);
        });

        return back()->with('status', 'Nilai kontrak disimpan.');
    }
}
