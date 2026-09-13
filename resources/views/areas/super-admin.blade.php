@extends('layouts.super-admin')

@section('title', 'Home | ARFAKA TOUR')

@section('main')
    <div class="flex flex-col gap-8">
        <section class="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-emerald-400/15 via-white/5 to-sky-400/10 p-6 sm:p-10">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-300">Super Admin Area · Pusat operasional</p>
            <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Selamat datang, {{ auth()->user()->name }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">Home menampilkan pekerjaan yang perlu ditangani. Analisis nominal, grafik, dan ekspor tersedia khusus di menu Keuangan.</p>
                </div>
                <a href="{{ route('super-admin.finance.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-300">Buka rekap keuangan →</a>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Perlu perhatian</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['label' => 'Pengeluaran menunggu review', 'value' => $metrics['pending_expenses'], 'href' => route('super-admin.finance.index'), 'tone' => 'text-amber-300'],
                    ['label' => 'Undangan TL belum dijawab', 'value' => $metrics['pending_assignments'], 'href' => route('super-admin.trips.index'), 'tone' => 'text-sky-300'],
                    ['label' => 'Trip ongoing', 'value' => $metrics['ongoing'], 'href' => route('super-admin.trips.index'), 'tone' => 'text-emerald-300'],
                    ['label' => 'Trip menunggu DP', 'value' => $metrics['draft'], 'href' => route('super-admin.trips.index'), 'tone' => 'text-slate-200'],
                ] as $metric)
                    <a href="{{ $metric['href'] }}" class="rounded-2xl border border-white/10 bg-slate-900 p-5 transition hover:border-white/25">
                        <p class="text-sm text-slate-400">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold {{ $metric['tone'] }}">{{ $metric['value'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
            <div class="rounded-2xl border border-white/10 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold">Trip aktif berikutnya</h2>
                    <a href="{{ route('super-admin.trips.index') }}" class="text-sm text-emerald-300">Lihat semua</a>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($upcomingTrips as $trip)
                        <a href="{{ route('super-admin.trips.show', $trip) }}" class="flex flex-col gap-2 rounded-xl bg-white/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><p class="truncate font-semibold">{{ $trip->name }}</p><p class="mt-1 truncate text-xs text-slate-400">{{ $trip->customer->name }} · {{ $trip->destination }}</p></div>
                            <div class="shrink-0 text-sm sm:text-right"><p>{{ $trip->start_date->format('d M Y') }}</p><p class="mt-1 text-xs text-emerald-300">{{ $trip->status->label() }}</p></div>
                        </a>
                    @empty
                        <p class="rounded-xl border border-dashed border-white/15 p-6 text-sm text-slate-400">Belum ada trip confirmed atau ongoing.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-white/10 p-5 sm:p-6">
                <h2 class="text-lg font-semibold">Data operasional</h2>
                <dl class="mt-5 grid grid-cols-2 gap-4">
                    <div class="rounded-xl bg-white/5 p-4"><dt class="text-xs text-slate-400">Customer</dt><dd class="mt-2 text-2xl font-semibold">{{ $metrics['customers'] }}</dd></div>
                    <div class="rounded-xl bg-white/5 p-4"><dt class="text-xs text-slate-400">TL aktif</dt><dd class="mt-2 text-2xl font-semibold">{{ $metrics['active_leaders'] }}</dd></div>
                    <div class="rounded-xl bg-white/5 p-4"><dt class="text-xs text-slate-400">Confirmed</dt><dd class="mt-2 text-2xl font-semibold">{{ $metrics['confirmed'] }}</dd></div>
                    <div class="rounded-xl bg-white/5 p-4"><dt class="text-xs text-slate-400">Ongoing</dt><dd class="mt-2 text-2xl font-semibold">{{ $metrics['ongoing'] }}</dd></div>
                </dl>
            </div>
        </section>
    </div>
@endsection
