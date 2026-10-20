<?php

namespace App\Http\Controllers;

use App\FinanceWorkbook;
use App\Models\BudgetItem;
use App\Models\Trip;
use App\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->query($request);
        $totals = $this->totals($query);
        if ($request->boolean('print')) {
            $trips = $query->with([
                'customer:id,name',
                'financeEntries' => fn ($entries) => $entries
                    ->with(['creator:id,name', 'leader:id,name', 'reviewer:id,name'])
                    ->orderBy('occurred_on')->orderBy('id'),
            ])->orderByDesc('start_date')->orderByDesc('id')->get();

            return view('finance.report-print', [
                'trips' => $trips,
                'totals' => $totals,
                'generatedAt' => now(),
                'filters' => $request->only(['search', 'from', 'to', 'status']),
            ]);
        }
        $chartTrips = (clone $query)->get(['trips.id', 'trips.start_date', 'trips.status']);
        $statusChart = collect(TripStatus::cases())->map(function (TripStatus $status) use ($chartTrips): array {
            return [
                'label' => $status->label(),
                'count' => $chartTrips->where('status', $status)->count(),
            ];
        });
        $monthlyChart = $this->monthlyChart($chartTrips);
        $monthlyMaximum = max(1, (int) $monthlyChart->max(fn (array $month): int => max($month['payments'], $month['expenses'])));
        $collectionRate = (int) $totals->contracts > 0
            ? min(100, (int) round(((int) $totals->payments / (int) $totals->contracts) * 100))
            : 0;
        $trips = $query->with('customer:id,name')->orderByDesc('start_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('finance.report', [
            'trips' => $trips,
            'totals' => $totals,
            'statuses' => TripStatus::cases(),
            'statusChart' => $statusChart,
            'monthlyChart' => $monthlyChart,
            'monthlyMaximum' => $monthlyMaximum,
            'collectionRate' => $collectionRate,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request)->with([
            'customer:id,name',
            'financeEntries' => fn ($entries) => $entries
                ->with(['creator:id,name', 'leader:id,name', 'reviewer:id,name'])
                ->orderBy('occurred_on')->orderBy('id'),
        ])->orderByDesc('start_date')->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fwrite($stream, "sep=;\r\n");
            fputcsv($stream, [
                'Kode Trip', 'Nama Trip', 'Customer', 'Tujuan', 'Periode Trip', 'Peserta', 'Status Trip',
                'Settlement', 'Nilai Kontrak', 'Pembayaran Diterima', 'Biaya Disetujui', 'Piutang',
                'Margin Sementara', 'Total RAB', 'ID Transaksi', 'Tanggal Transaksi', 'Jenis Transaksi',
                'Kategori', 'Status Transaksi', 'Nominal Transaksi', 'Keterangan', 'TL Terkait',
                'Dicatat Oleh', 'Diperiksa Oleh', 'Tanggal Pemeriksaan', 'Alasan Pemeriksaan',
                'Waktu Foto', 'Lokasi Foto', 'Akurasi Lokasi', 'Ukuran Asli', 'Ukuran Tersimpan',
            ], ';', '"', '');
            foreach ($query->lazy(100) as $trip) {
                $entries = $trip->financeEntries->isEmpty() ? collect([null]) : $trip->financeEntries;
                foreach ($entries as $entry) {
                    fputcsv($stream, [
                        $trip->code,
                        $this->safeCell($trip->name),
                        $this->safeCell($trip->customer->name),
                        $this->safeCell($trip->destination),
                        $trip->start_date->format('d/m/Y').' - '.$trip->end_date->format('d/m/Y'),
                        $trip->participant_count.' orang',
                        $trip->status->label(),
                        $trip->finances_locked_at ? 'Terkunci '.$trip->finances_locked_at->format('d/m/Y H:i') : 'Terbuka',
                        $this->rupiah($trip->contract_amount),
                        $this->rupiah((int) $trip->payment_total),
                        $this->rupiah((int) $trip->expense_total),
                        $trip->contract_amount === null ? 'Belum diisi' : $this->rupiah($trip->contract_amount - (int) $trip->payment_total),
                        $trip->contract_amount === null ? 'Belum diisi' : $this->rupiah($trip->contract_amount - (int) $trip->expense_total),
                        $this->rupiah((int) $trip->budget_total),
                        $entry?->id,
                        $entry?->occurred_on?->format('d/m/Y'),
                        $entry?->type?->label(),
                        $entry?->category?->label(),
                        $entry === null ? '' : $this->entryStatusLabel($entry->status),
                        $entry === null ? '' : $this->rupiah($entry->amount),
                        $entry === null ? '' : $this->safeCell($entry->description),
                        $this->safeOptionalCell($entry?->leader?->name ?? ($entry === null ? null : 'Perusahaan')),
                        $this->safeOptionalCell($entry?->creator?->name),
                        $this->safeOptionalCell($entry?->reviewer?->name),
                        $entry?->reviewed_at?->format('d/m/Y H:i'),
                        $entry?->review_reason === null ? '' : $this->safeCell($entry->review_reason),
                        $entry?->captured_at?->format('d/m/Y H:i:s'),
                        $entry?->latitude === null ? '' : 'GPS '.$entry->latitude.', '.$entry->longitude,
                        $entry?->location_accuracy === null ? '' : $entry->location_accuracy.' meter',
                        $this->fileSize($entry?->receipt_original_size),
                        $this->fileSize($entry?->receipt_stored_size),
                    ], ';', '"', '');
                }
            }
            fclose($stream);
        }, 'rekap-keuangan-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function exportExcel(Request $request, FinanceWorkbook $workbook): StreamedResponse
    {
        $query = $this->query($request)->orderByDesc('start_date')->orderByDesc('id');
        $totals = $this->totals($query);
        $filters = $request->only(['search', 'from', 'to', 'status']);

        return response()->streamDownload(
            fn () => $workbook->write($query, $totals, $filters),
            'rekap-keuangan-arfaka-'.now()->format('Y-m-d-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    /** @param Builder<Trip> $query */
    private function totals(Builder $query): stdClass
    {
        return DB::query()->fromSub((clone $query)->toBase(), 'report')
            ->selectRaw('COUNT(*) as trips, SUM(contract_amount) as contracts, SUM(payment_total) as payments, SUM(expense_total) as expenses')
            ->selectRaw('SUM(CASE WHEN contract_amount IS NOT NULL THEN contract_amount - COALESCE(payment_total, 0) ELSE 0 END) as receivables')
            ->selectRaw('SUM(CASE WHEN contract_amount IS NOT NULL THEN contract_amount - COALESCE(expense_total, 0) ELSE 0 END) as profit')
            ->selectRaw('SUM(CASE WHEN contract_amount IS NULL THEN 1 ELSE 0 END) as missing_contracts')
            ->selectRaw('SUM(pending_count) as pending_count')
            ->selectRaw('SUM(COALESCE(payment_total, 0) - COALESCE(company_expenses, 0) - COALESCE(advance_total, 0) - COALESCE(reimbursement_total, 0) + COALESCE(return_total, 0)) as cash')
            ->first();
    }

    /** @return Builder<Trip> */
    private function query(Request $request): Builder
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', Rule::enum(TripStatus::class)],
        ]);
        $query = Trip::query()
            ->when($data['search'] ?? null, function ($query, $search): void {
                $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
            })
            ->when($data['from'] ?? null, fn ($query, $from) => $query->where('start_date', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->where('start_date', '<=', $to))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->withCount(['financeEntries as pending_count' => fn ($query) => $query->where('status', 'pending')]);

        foreach (['payment', 'expense', 'advance', 'return', 'reimbursement'] as $type) {
            $query->withSum(['financeEntries as '.$type.'_total' => fn ($query) => $query->where('status', 'approved')->where('type', $type)], 'amount');
        }

        return $query->withSum(['financeEntries as company_expenses' => fn ($query) => $query->where('status', 'approved')->where('type', 'expense')->whereNull('leader_id')], 'amount')
            ->addSelect(['budget_total' => BudgetItem::query()->selectRaw('COALESCE(SUM(quantity * unit_amount), 0)')->whereColumn('trip_id', 'trips.id')]);
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[\\s]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    private function safeOptionalCell(?string $value): string
    {
        return $value === null ? '' : $this->safeCell($value);
    }

    /** @param Collection<int, Trip> $trips
     * @return Collection<int, array{label: string, payments: int, expenses: int}>
     */
    private function monthlyChart(Collection $trips): Collection
    {
        return $trips->groupBy(fn (Trip $trip): string => $trip->start_date->format('Y-m'))
            ->sortKeys()
            ->take(-6)
            ->map(function (Collection $monthTrips, string $month): array {
                return [
                    'label' => $monthTrips->first()->start_date->translatedFormat('M Y'),
                    'payments' => (int) $monthTrips->sum(fn (Trip $trip): int => (int) $trip->payment_total),
                    'expenses' => (int) $monthTrips->sum(fn (Trip $trip): int => (int) $trip->expense_total),
                ];
            })->values();
    }

    private function rupiah(?int $amount): string
    {
        return $amount === null ? 'Belum diisi' : 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function fileSize(?int $bytes): string
    {
        return $bytes === null ? '' : number_format($bytes / 1024, 1, ',', '.').' KB';
    }

    private function entryStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu pemeriksaan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'void' => 'Dibatalkan',
            default => $status,
        };
    }
}
