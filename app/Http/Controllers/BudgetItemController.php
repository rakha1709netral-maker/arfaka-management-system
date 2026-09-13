<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use App\Models\BudgetItem;
use App\Models\Trip;
use App\TripFinance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BudgetItemController extends Controller
{
    public function store(Request $request, Trip $trip, TripFinance $finance): RedirectResponse
    {
        $data = $this->validated($request);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($data, $finance, $request): void {
            $item = $locked->budgetItems()->create($data);
            $finance->audit($locked, $request->user(), 'budget.created', ['item_id' => $item->id, 'item' => $item->only(['description', 'category', 'quantity', 'unit_amount'])]);
        });

        return back()->with('status', 'Item RAB ditambahkan.');
    }

    public function update(Request $request, Trip $trip, BudgetItem $budgetItem, TripFinance $finance): RedirectResponse
    {
        abort_unless($budgetItem->trip_id === $trip->id, 404);
        $data = $this->validated($request);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($data, $budgetItem, $finance, $request): void {
            $item = $locked->budgetItems()->lockForUpdate()->findOrFail($budgetItem->id);
            $before = $item->only(['description', 'category', 'quantity', 'unit_amount']);
            $item->update($data);
            $finance->audit($locked, $request->user(), 'budget.updated', ['item_id' => $item->id, 'before' => $before, 'after' => $data]);
        });

        return back()->with('status', 'Item RAB diperbarui.');
    }

    public function destroy(Request $request, Trip $trip, BudgetItem $budgetItem, TripFinance $finance): RedirectResponse
    {
        abort_unless($budgetItem->trip_id === $trip->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($budgetItem, $finance, $request, $data): void {
            $item = $locked->budgetItems()->lockForUpdate()->findOrFail($budgetItem->id);
            $item->delete();
            $finance->audit($locked, $request->user(), 'budget.archived', ['item_id' => $item->id, 'total' => $item->total()], $data['reason']);
        });

        return back()->with('status', 'Item RAB diarsipkan, histori tetap tersimpan.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_amount' => ['required', 'integer', 'min:1', 'max:10000000'],
        ]);
    }
}
