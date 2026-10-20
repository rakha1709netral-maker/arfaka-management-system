@extends('layouts.super-admin')
@section('title', 'Laporan Keuangan | ARFAKA TOUR')
@section('main')
<style>
    .report-paper { background: #fff; color: #172033; }
    .report-table { width: 100%; border-collapse: collapse; }
    .report-table th, .report-table td { border-bottom: 1px solid #dbe3e9; padding: 9px 7px; vertical-align: top; }
    .report-table th { background: #e8f6f4; color: #153b42; font-size: 11px; text-align: left; }
    .report-table tbody tr:nth-child(even) { background: #f8fafc; }
    @media print {
        @page { size: A4 landscape; margin: 12mm; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        main { max-width: none !important; padding: 0 !important; }
        .report-paper { padding: 0 !important; border: 0 !important; box-shadow: none !important; }
        .report-table { font-size: 8pt !important; }
        .report-table th { background: #e8f6f4 !important; }
        .report-table thead { display: table-header-group; }
        .report-table tbody tr:nth-child(even) { background: #f8fafc !important; }
        .report-table tr, .report-summary { break-inside: avoid; }
        .report-trip h3 { break-after: avoid; }
    }
</style>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-400/30 bg-sky-400/10 p-4 text-sm text-sky-100 print:hidden" data-auto-print>
    <div><p class="font-semibold">Laporan siap dicetak atau disimpan sebagai PDF.</p><p class="mt-1">Di iPhone, buka pratinjau cetak lalu gunakan Bagikan → Simpan ke File.</p></div>
    <div class="flex flex-wrap gap-2"><button type="button" data-print class="rounded-lg bg-sky-300 px-4 py-2 font-semibold text-slate-950">Buka dialog cetak</button><a href="{{ route('super-admin.finance.index', $filters) }}" class="rounded-lg border border-white/20 px-4 py-2">Kembali</a></div>
</div>
<article class="report-paper rounded-2xl border border-slate-200 p-5 shadow-xl sm:p-8">
    <div class="flex flex-wrap items-start justify-between gap-5 border-b-2 border-teal-700 pb-5">
        <div class="flex items-center gap-4">
            <img src="{{ asset('images/arfaka-logo.png') }}" alt="Logo Arfaka Tour and Travel" class="h-20 w-40 object-contain">
            <div><p class="text-xs font-bold uppercase tracking-widest text-teal-700">Laporan internal</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Rekapitulasi Keuangan Trip</h1><p class="text-sm text-slate-600">ARFAKA TOUR and Travel</p></div>
        </div>
        <div class="text-left text-xs leading-6 text-slate-600 sm:text-right"><p>Dibuat: {{ $generatedAt->format('d/m/Y H:i') }} WIB</p><p>Jumlah trip: {{ (int) $totals->trips }}</p><p>Mata uang: Rupiah (IDR)</p></div>
    </div>

    <div class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <div><span class="text-slate-500">Pencarian</span><p class="mt-1 font-semibold text-slate-800">{{ ! empty($filters['search']) ? $filters['search'] : 'Semua trip' }}</p></div>
        <div><span class="text-slate-500">Mulai trip dari</span><p class="mt-1 font-semibold text-slate-800">{{ ! empty($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : 'Tanpa batas' }}</p></div>
        <div><span class="text-slate-500">Mulai trip sampai</span><p class="mt-1 font-semibold text-slate-800">{{ ! empty($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') : 'Tanpa batas' }}</p></div>
        <div><span class="text-slate-500">Status trip</span><p class="mt-1 font-semibold text-slate-800">{{ ! empty($filters['status']) ? \App\TripStatus::from($filters['status'])->label() : 'Semua status' }}</p></div>
    </div>
    <p class="mt-3 text-xs leading-relaxed text-slate-600">Filter tanggal berdasarkan mulai trip. Nominal adalah total sepanjang umur trip yang sesuai filter. Hanya transaksi berstatus disetujui yang memengaruhi ringkasan; transaksi menunggu, ditolak, dan dibatalkan ditampilkan untuk pemeriksaan.</p>

    <div class="report-summary mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Nilai kontrak', $totals->contracts], ['Pembayaran diterima', $totals->payments], ['Piutang customer', $totals->receivables],
            ['Biaya disetujui', $totals->expenses], ['Margin kontrak sementara', $totals->profit], ['Arus kas bersih perusahaan', $totals->cash],
        ] as [$label, $amount])
            <div class="rounded-lg border border-slate-200 p-3"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-lg font-bold tabular-nums text-slate-900">Rp {{ number_format((int) $amount, 0, ',', '.') }}</p></div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-slate-600">{{ (int) $totals->pending_count }} transaksi menunggu pemeriksaan; {{ (int) $totals->missing_contracts }} trip belum memiliki nilai kontrak. Margin bersifat sementara, bukan laba akuntansi final.</p>

    <h2 class="mt-8 border-b border-teal-700 pb-2 text-base font-bold text-slate-900">Ringkasan per trip</h2>
    <div class="overflow-x-auto"><table class="report-table mt-3 text-xs">
        <thead><tr><th>Trip / customer</th><th>Periode / status</th><th>Kontrak</th><th>Pembayaran</th><th>Biaya disetujui</th><th>Piutang</th><th>Margin sementara</th></tr></thead>
        <tbody>
            @forelse ($trips as $trip)
                <tr><td><strong>{{ $trip->name }}</strong><br>{{ $trip->code }}<br>{{ $trip->customer->name }}</td><td>{{ $trip->start_date->format('d/m/Y') }} – {{ $trip->end_date->format('d/m/Y') }}<br>{{ $trip->status->label() }}</td><td>{{ $trip->contract_amount === null ? 'Belum diisi' : 'Rp '.number_format($trip->contract_amount, 0, ',', '.') }}</td><td>Rp {{ number_format((int) $trip->payment_total, 0, ',', '.') }}</td><td>Rp {{ number_format((int) $trip->expense_total, 0, ',', '.') }}</td><td>{{ $trip->contract_amount === null ? '—' : 'Rp '.number_format($trip->contract_amount - (int) $trip->payment_total, 0, ',', '.') }}</td><td>{{ $trip->contract_amount === null ? '—' : 'Rp '.number_format($trip->contract_amount - (int) $trip->expense_total, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="7">Belum ada trip sesuai filter.</td></tr>
            @endforelse
        </tbody>
    </table></div>

    <h2 class="mt-8 border-b border-teal-700 pb-2 text-base font-bold text-slate-900">Rincian transaksi</h2>
    @foreach ($trips as $trip)
        <section class="report-trip mt-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2"><h3 class="text-sm font-bold text-slate-900">{{ $trip->name }} <span class="font-normal text-slate-500">({{ $trip->code }})</span></h3><span class="text-xs text-slate-600">{{ $trip->destination }} · {{ $trip->customer->name }}</span></div>
            <div class="overflow-x-auto"><table class="report-table mt-2 text-xs">
                <thead><tr><th>Tanggal</th><th>Jenis / kategori</th><th>Keterangan</th><th>TL / pencatat</th><th>Status</th><th>Nominal</th><th>Catatan pemeriksaan</th></tr></thead>
                <tbody>
                    @forelse ($trip->financeEntries as $entry)
                        @php($statusLabel = match ($entry->status) { 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'void' => 'Dibatalkan', default => 'Menunggu' })
                        <tr><td>{{ $entry->occurred_on->format('d/m/Y') }}</td><td>{{ $entry->type->label() }}<br>{{ $entry->category?->label() }}</td><td>{{ $entry->description }}</td><td>{{ $entry->leader?->name ?? 'Perusahaan' }}<br><span class="text-slate-500">{{ $entry->creator?->name }}</span></td><td><strong>{{ $statusLabel }}</strong></td><td class="whitespace-nowrap">Rp {{ number_format($entry->amount, 0, ',', '.') }}</td><td>{{ $entry->review_reason ?: '—' }}@if ($entry->reviewed_at)<br><span class="text-slate-500">{{ $entry->reviewer?->name }} · {{ $entry->reviewed_at->format('d/m/Y H:i') }}</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="7">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </section>
    @endforeach
    <div class="mt-8 border-t border-slate-300 pt-3 text-xs text-slate-500">ARFAKA TOUR and Travel · Dokumen internal · Dicetak dari sistem pada {{ $generatedAt->format('d/m/Y H:i') }} WIB</div>
</article>
@endsection
