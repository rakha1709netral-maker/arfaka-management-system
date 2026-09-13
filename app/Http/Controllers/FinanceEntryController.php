<?php

namespace App\Http\Controllers;

use App\FinanceType;
use App\Http\Requests\StoreFinanceEntryRequest;
use App\Models\FinanceEntry;
use App\Models\Trip;
use App\Models\User;
use App\TripAssignmentStatus;
use App\TripFinance;
use App\TripStatus;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FinanceEntryController extends Controller
{
    public function store(StoreFinanceEntryRequest $request, Trip $trip, TripFinance $finance): RedirectResponse
    {
        $receiptPath = null;
        try {
            $finance->mutate($trip, $request->user(), function (Trip $locked) use ($request, $finance, &$receiptPath): void {
                $duplicate = FinanceEntry::query()->where('submission_token', $request->validated('submission_token'))->first();
                if ($duplicate !== null) {
                    abort_unless($duplicate->trip_id === $locked->id && $duplicate->created_by === $request->user()->id, 409);

                    return;
                }

                $isLeader = $request->user()->role === UserRole::TourLeader;
                if ($isLeader && $locked->status !== TripStatus::Ongoing) {
                    throw ValidationException::withMessages([
                        'finance' => 'Pengeluaran baru dapat diajukan setelah tugas diterima dan trip berstatus ongoing.',
                    ]);
                }
                $entry = new FinanceEntry($request->safe()->only(['type', 'category', 'amount', 'occurred_on', 'description']));
                $entry->trip_id = $locked->id;
                $entry->created_by = $request->user()->id;
                $entry->status = $isLeader ? 'pending' : 'approved';
                $entry->submission_token = $request->validated('submission_token');
                $entry->leader_id = $isLeader ? $request->user()->id : $request->validated('leader_id');
                if ($entry->type === FinanceType::Payment) {
                    $entry->leader_id = null;
                }
                if ($isLeader) {
                    $entry->captured_at = $request->date('captured_at');
                    $entry->latitude = $request->validated('latitude');
                    $entry->longitude = $request->validated('longitude');
                    $entry->location_accuracy = $request->validated('location_accuracy');
                    $entry->receipt_original_size = $request->validated('receipt_original_size');
                }

                if ($entry->leader_id !== null) {
                    $leader = User::query()->where('role', UserRole::TourLeader)->lockForUpdate()->find($entry->leader_id);
                    $currentAssignment = $locked->assignments()
                        ->where('user_id', $entry->leader_id)
                        ->where('status', TripAssignmentStatus::Accepted)
                        ->exists();
                    $historicEntry = $locked->financeEntries()->where('leader_id', $entry->leader_id)->exists();
                    $isSettlement = in_array($entry->type, [FinanceType::Return, FinanceType::Reimbursement], true);
                    if ($leader === null || ($isSettlement ? ! ($currentAssignment || $historicEntry) : (! $leader->is_active || ! $currentAssignment))) {
                        throw ValidationException::withMessages(['leader_id' => 'Pilih TL yang ditugaskan. Saldo TL lama hanya dapat diselesaikan lewat pengembalian atau reimbursement.']);
                    }
                }
                $finance->validateEntry($locked, $entry);
                if ($isLeader) {
                    $receiptContents = $request->receiptContents();
                    $receiptPath = $locked->id.'/'.Str::uuid().'.jpg';
                    if (! Storage::disk('receipts')->put($receiptPath, $receiptContents)) {
                        throw new RuntimeException('Bukti transaksi gagal disimpan.');
                    }
                    $entry->receipt_path = $receiptPath;
                    $entry->receipt_stored_size = strlen($receiptContents);
                } elseif ($request->hasFile('receipt')) {
                    $receiptPath = $request->file('receipt')->store((string) $locked->id, 'receipts');
                    $entry->receipt_path = $receiptPath;
                    $entry->receipt_stored_size = $request->file('receipt')->getSize();
                }
                $entry->save();
                $finance->audit($locked, $request->user(), 'entry.created', ['entry_id' => $entry->id, 'type' => $entry->type->value, 'amount' => $entry->amount, 'status' => $entry->status]);
                if ($entry->type === FinanceType::Payment && $locked->status === TripStatus::Draft) {
                    $locked->update(['status' => TripStatus::Confirmed]);
                    $finance->audit($locked, $request->user(), 'trip.confirmed_by_payment', [
                        'entry_id' => $entry->id, 'amount' => $entry->amount,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($receiptPath !== null) {
                Storage::disk('receipts')->delete($receiptPath);
            }
            throw $exception;
        }

        return back()->with('status', 'Transaksi tersimpan. Pengeluaran TL menunggu pemeriksaan admin.');
    }

    public function update(Request $request, Trip $trip, FinanceEntry $entry, TripFinance $finance): RedirectResponse
    {
        abort_unless($entry->trip_id === $trip->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'void'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $finance->mutate($trip, $request->user(), function (Trip $locked) use ($entry, $data, $finance, $request): void {
            $entry = $locked->financeEntries()->lockForUpdate()->findOrFail($entry->id);
            $allowed = ($entry->status === 'pending' && in_array($data['status'], ['approved', 'rejected'], true))
                || ($entry->status === 'approved' && $data['status'] === 'void');
            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'Transaksi sudah diproses. Muat ulang halaman.']);
            }
            $previous = $entry->status;
            $entry->status = $data['status'];
            $entry->reviewed_by = $request->user()->id;
            $entry->review_reason = $data['reason'];
            $entry->reviewed_at = now();
            $entry->save();
            $finance->audit($locked, $request->user(), 'entry.'.$data['status'], ['entry_id' => $entry->id, 'previous_status' => $previous, 'amount' => $entry->amount], $data['reason']);
        });

        return back()->with('status', 'Status transaksi diperbarui dan tercatat di histori.');
    }

    public function receipt(Request $request, Trip $trip, FinanceEntry $entry): BinaryFileResponse
    {
        Gate::authorize('view', $trip);
        abort_unless($entry->trip_id === $trip->id, 404);
        if ($request->user()->role === UserRole::TourLeader) {
            abort_unless($entry->leader_id === $request->user()->id, 404);
        }
        abort_if($entry->receipt_path === null || ! Storage::disk('receipts')->exists($entry->receipt_path), 404);

        $path = Storage::disk('receipts')->path($entry->receipt_path);
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        $response = $request->boolean('download')
            ? response()->download($path, 'bukti-'.$entry->id.'.'.pathinfo($entry->receipt_path, PATHINFO_EXTENSION), $headers)
            : response()->file($path, $headers);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
