<?php

namespace App;

use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TripFinance
{
    public const MAX_AMOUNT = 1000000000000;

    public function mutate(Trip $trip, User $actor, Closure $operation): mixed
    {
        return DB::transaction(function () use ($trip, $actor, $operation): mixed {
            $locked = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            Gate::forUser($actor)->authorize('view', $locked);
            if ($locked->finances_locked_at !== null) {
                throw ValidationException::withMessages(['finance' => 'Keuangan trip sudah dikunci. Buka kembali settlement terlebih dahulu.']);
            }

            return $operation($locked);
        });
    }

    /** @param array<string, mixed> $details */
    public function audit(Trip $trip, User $actor, string $action, array $details = [], ?string $reason = null): void
    {
        $trip->financeAudits()->create([
            'user_id' => $actor->id, 'action' => $action, 'details' => $details, 'reason' => $reason,
        ]);
    }

    /** @return array<string, mixed> */
    public function summary(Trip $trip, ?int $leaderId = null): array
    {
        $groups = $trip->financeEntries()
            ->when($leaderId !== null, fn ($query) => $query->where('leader_id', $leaderId))
            ->select(['type', 'leader_id', 'status', 'category'])
            ->selectRaw('SUM(amount) as total, COUNT(*) as entry_count')
            ->groupBy('type', 'leader_id', 'status', 'category')->get();

        $totals = ['payment' => 0, 'expense' => 0, 'advance' => 0, 'return' => 0, 'reimbursement' => 0];
        $balances = [];
        $categories = [];
        $companyExpenses = 0;
        $pending = 0;
        $pendingCount = 0;
        foreach ($groups as $group) {
            if ($group->status === 'pending') {
                $pending += (int) $group->total;
                $pendingCount += (int) $group->entry_count;
            }
            if ($group->status !== 'approved') {
                continue;
            }

            $amount = (int) $group->total;
            $totals[$group->type->value] += $amount;
            if ($group->type === FinanceType::Expense) {
                $key = $group->category?->value ?? ExpenseCategory::Other->value;
                $categories[$key] = ($categories[$key] ?? 0) + $amount;
                if ($group->leader_id === null) {
                    $companyExpenses += $amount;
                }
            }
            if ($group->leader_id !== null) {
                $sign = match ($group->type) {
                    FinanceType::Advance, FinanceType::Reimbursement => 1,
                    FinanceType::Expense, FinanceType::Return => -1,
                    default => 0,
                };
                $balances[$group->leader_id] = ($balances[$group->leader_id] ?? 0) + $sign * $amount;
            }
        }

        $budget = (int) $trip->budgetItems()->selectRaw('COALESCE(SUM(quantity * unit_amount), 0) as total')->value('total');

        return $totals + [
            'budget' => $budget, 'pending' => $pending, 'pending_count' => $pendingCount,
            'balances' => $balances, 'categories' => $categories,
            'profit' => $trip->contract_amount === null ? null : $trip->contract_amount - $totals['expense'],
            'receivable' => $trip->contract_amount === null ? null : $trip->contract_amount - $totals['payment'],
            'cash' => $totals['payment'] - $companyExpenses - $totals['advance'] - $totals['reimbursement'] + $totals['return'],
        ];
    }

    public function validateEntry(Trip $trip, FinanceEntry $entry): void
    {
        $summary = $this->summary($trip);
        $balance = $summary['balances'][$entry->leader_id] ?? 0;
        $message = match (true) {
            $entry->type === FinanceType::Payment && $trip->contract_amount === null => 'Isi nilai kontrak sebelum mencatat pembayaran.',
            $entry->type === FinanceType::Payment && $trip->contract_amount < $summary['payment'] + $entry->amount => 'Pembayaran melebihi sisa tagihan customer.',
            $entry->type === FinanceType::Return && $entry->amount > max(0, $balance) => 'Pengembalian melebihi saldo TL yang harus dikembalikan.',
            $entry->type === FinanceType::Reimbursement && $entry->amount > max(0, -$balance) => 'Reimbursement melebihi biaya TL yang belum diganti.',
            default => null,
        };
        if ($message !== null) {
            throw ValidationException::withMessages(['amount' => $message]);
        }
    }
}
