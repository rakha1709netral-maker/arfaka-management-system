@extends('layouts.super-admin')
@section('title', 'Rekap Keuangan | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-400">Super Admin Area · Finance</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Rekapitulasi keuangan</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-400">Pantau pembayaran, biaya operasional, dan hasil setiap perjalanan dalam satu tempat.</p>
        </div>
        <div class="flex flex-wrap gap-2 print:hidden">
            <a href="{{ route('super-admin.finance.excel', request()->only(['search', 'from', 'to', 'status'])) }}" class="rounded-xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-emerald-300">Ekspor Excel</a>
            <a href="{{ route('super-admin.finance.export', request()->only(['search', 'from', 'to', 'status'])) }}" class="rounded-xl border border-white/20 px-4 py-3 text-sm">CSV data mentah</a>
            <a href="{{ route('super-admin.finance.index', [...request()->only(['search', 'from', 'to', 'status']), 'print' => 1]) }}" class="rounded-xl border border-white/20 px-4 py-3 text-sm">Cetak / Simpan PDF</a>
        </div>
    </div>
    @if (request()->boolean('print'))
        <div class="rounded-2xl border border-sky-400/30 bg-sky-400/10 p-5 text-sm text-sky-100 print:hidden" data-auto-print>
            <p class="font-semibold">Laporan siap dicetak atau disimpan sebagai PDF.</p>
            <p class="mt-1 text-sky-100/80">Jika dialog perangkat tidak terbuka otomatis, gunakan tombol di bawah. Di iPhone, buka pratinjau cetak lalu gunakan Bagikan → Simpan ke File.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" data-print class="rounded-xl bg-sky-300 px-4 py-2.5 font-semibold text-slate-950">Buka dialog cetak</button>
                <a href="{{ route('super-admin.finance.index', request()->only(['search', 'from', 'to', 'status'])) }}" class="rounded-xl border border-white/20 px-4 py-2.5">Kembali ke laporan</a>
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-400/30 bg-red-400/10 p-4 text-sm text-red-200">{{ $errors->first() }}</div>
    @endif
    <form method="GET" action="{{ route('super-admin.finance.index') }}" class="grid gap-4 rounded-2xl border border-white/10 p-5 sm:grid-cols-2 lg:grid-cols-5 print:hidden">
        <x-field name="search" label="Cari trip / kode" :value="request('search')" />
        <x-field name="from" label="Mulai trip dari" type="date" :value="request('from')" />
        <x-field name="to" label="Mulai trip sampai" type="date" :value="request('to')" />
        <x-field name="status" label="Status trip" type="select">
            <option value="">Semua status</option>
            @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
        </x-field>
        <div class="flex items-end gap-3"><button class="rounded-xl bg-white px-5 py-3 font-semibold text-slate-950">Terapkan</button><a class="py-3 text-sm text-slate-400" href="{{ route('super-admin.finance.index') }}">Reset</a></div>
    </form>
    <p class="text-xs text-slate-400">Periode berdasarkan tanggal mulai trip, bukan tanggal transaksi. Angka merupakan total sepanjang umur trip yang cocok dengan filter ({{ $totals->trips }} trip). Biaya pending/ditolak/dibatalkan tidak dihitung.</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-money-card label="Nilai kontrak" :amount="(int) $totals->contracts" hint="Nilai kesepakatan customer" />
        <x-money-card label="Pembayaran diterima" :amount="(int) $totals->payments" hint="DP dan pembayaran lanjutan" />
        <x-money-card label="Piutang customer" :amount="(int) $totals->receivables" hint="Kontrak dikurangi pembayaran" />
        <x-money-card label="Biaya disetujui" :amount="(int) $totals->expenses" hint="Biaya perusahaan dan biaya melalui TL, termasuk honor/tip/komisi" />
        <x-money-card label="Margin kontrak sementara" :amount="(int) $totals->profit" hint="Kontrak dikurangi biaya disetujui; bukan laba akuntansi final" />
        <x-money-card label="Arus kas bersih perusahaan" :amount="(int) $totals->cash" hint="Pembayaran − biaya langsung − uang ke TL + pengembalian TL" />
    </div>
    <section class="grid gap-5 xl:grid-cols-2" aria-labelledby="finance-visual-title">
        <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
            <div class="flex items-start justify-between gap-4">
                <div><h2 id="finance-visual-title" class="text-lg font-semibold">Penerimaan dan biaya per bulan trip</h2><p class="mt-1 text-xs text-slate-400">Enam bulan terakhir dari hasil filter, menurut tanggal mulai trip.</p></div>
                <div class="flex shrink-0 gap-3 text-xs"><span class="text-emerald-300">● Diterima</span><span class="text-amber-300">● Biaya</span></div>
            </div>
            <div class="mt-6 flex flex-col gap-5">
                @forelse ($monthlyChart as $month)
                    <div>
                        <div class="mb-2 flex justify-between gap-3 text-xs"><span class="font-medium text-slate-200">{{ $month['label'] }}</span><span class="text-slate-400">Rp {{ number_format($month['payments'], 0, ',', '.') }} / Rp {{ number_format($month['expenses'], 0, ',', '.') }}</span></div>
                        <div class="grid gap-1" aria-label="{{ $month['label'] }}: diterima Rp {{ number_format($month['payments'], 0, ',', '.') }}, biaya Rp {{ number_format($month['expenses'], 0, ',', '.') }}">
                            <div class="h-3 rounded-full bg-white/5"><div class="h-3 min-w-1 rounded-full bg-emerald-400" style="width: {{ round(($month['payments'] / $monthlyMaximum) * 100, 2) }}%"></div></div>
                            <div class="h-3 rounded-full bg-white/5"><div class="h-3 min-w-1 rounded-full bg-amber-300" style="width: {{ round(($month['expenses'] / $monthlyMaximum) * 100, 2) }}%"></div></div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-400">Belum ada data untuk divisualkan.</p>
                @endforelse
            </div>
        </div>
        <div class="flex flex-col gap-5">
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <div class="flex items-end justify-between gap-4"><div><h2 class="text-lg font-semibold">Progres pembayaran customer</h2><p class="mt-1 text-xs text-slate-400">Pembayaran diterima dibanding total kontrak yang terisi.</p></div><strong class="text-3xl text-emerald-300">{{ $collectionRate }}%</strong></div>
                <div class="mt-5 h-4 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $collectionRate }}"><div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-cyan-300" style="width: {{ $collectionRate }}%"></div></div>
            </div>
            <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                <h2 class="text-lg font-semibold">Komposisi status trip</h2>
                @php($maximumStatusCount = max(1, (int) $statusChart->max('count')))
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($statusChart as $status)
                        <div class="grid grid-cols-[7rem_1fr_2rem] items-center gap-3 text-xs"><span class="text-slate-300">{{ $status['label'] }}</span><div class="h-2 rounded-full bg-white/5"><div class="h-2 min-w-1 rounded-full bg-sky-400" style="width: {{ round(($status['count'] / $maximumStatusCount) * 100, 2) }}%"></div></div><span class="text-right font-semibold">{{ $status['count'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    <div class="flex flex-wrap gap-3 text-sm">
        <p class="rounded-full border border-amber-400/30 px-4 py-2 text-amber-200">{{ (int) $totals->pending_count }} transaksi menunggu pemeriksaan</p>
        @if ($totals->missing_contracts)
            <p class="rounded-full border border-white/20 px-4 py-2 text-slate-300">{{ $totals->missing_contracts }} trip belum memiliki nilai kontrak; tidak masuk hitungan margin/piutang.</p>
        @endif
    </div>
    <section class="overflow-hidden rounded-2xl border border-white/10">
        <div class="flex items-center justify-between gap-3 border-b border-white/10 p-5"><h2 class="text-lg font-semibold">Keuangan per trip</h2><a href="{{ route('super-admin.trips.create') }}" class="text-sm text-emerald-400 print:hidden">+ Buat trip</a></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-200 text-left text-sm">
                <thead class="bg-slate-900 text-slate-400"><tr><th class="p-4">Trip / Customer</th><th class="p-4">Kontrak</th><th class="p-4">Diterima</th><th class="p-4">Biaya</th><th class="p-4">Sisa tagihan</th><th class="p-4">Margin</th></tr></thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($trips as $trip)
                        <tr>
                            <td class="p-4"><a class="font-semibold text-emerald-300" href="{{ route('super-admin.trips.finance.show', $trip) }}">{{ $trip->name }}</a><p class="mt-1 text-xs text-slate-400">{{ $trip->customer->name }} · {{ $trip->start_date->format('d/m/Y') }}</p><p class="mt-1 text-xs">{{ $trip->status->label() }}{{ $trip->finances_locked_at ? ' · Terkunci' : '' }}{{ $trip->pending_count ? ' · '.$trip->pending_count.' pending' : '' }}</p></td>
                            <td class="p-4 tabular-nums">{{ $trip->contract_amount === null ? 'Belum diisi' : number_format($trip->contract_amount, 0, ',', '.') }}</td>
                            <td class="p-4 tabular-nums">{{ number_format((int) $trip->payment_total, 0, ',', '.') }}</td>
                            <td class="p-4 tabular-nums">{{ number_format((int) $trip->expense_total, 0, ',', '.') }}</td>
                            <td class="p-4 tabular-nums">{{ $trip->contract_amount === null ? '—' : number_format($trip->contract_amount - (int) $trip->payment_total, 0, ',', '.') }}</td>
                            <td class="p-4 tabular-nums">{{ $trip->contract_amount === null ? '—' : number_format($trip->contract_amount - (int) $trip->expense_total, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-slate-400">Belum ada trip sesuai filter. Tambahkan customer dan trip untuk mulai mencatat keuangan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $trips->links() }}
    <p class="text-xs text-slate-500">Seluruh nominal IDR. Cetak, Excel (.xlsx), dan CSV mencakup semua trip sesuai filter. CSV hanya data teks; warna dan tata letak tersedia di ekspor Excel.</p>
</div>
@endsection
