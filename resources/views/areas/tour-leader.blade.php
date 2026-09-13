@extends('layouts.tour-leader')

@section('title', 'Trip Saya | ARFAKA TOUR')

@section('main')
    <div class="flex flex-col gap-8">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-sky-300">Tour Leader</p>
            <h1 class="mt-2 text-3xl font-semibold">Trip Saya</h1>
            <p class="mt-2 text-sm text-slate-400">Tinjau undangan tugas terlebih dahulu. Pengeluaran hanya dapat diajukan setelah trip ongoing.</p>
        </div>

        @if ($invitations->isNotEmpty())
            <section class="rounded-2xl border border-sky-400/30 bg-sky-400/10 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-sky-100">Undangan tugas baru</h2>
                    <span class="rounded-full bg-sky-300 px-3 py-1 text-xs font-bold text-slate-950">{{ $invitations->count() }}</span>
                </div>
                <div class="mt-5 flex flex-col gap-4">
                    @foreach ($invitations as $assignment)
                        <article class="rounded-xl border border-white/10 bg-slate-950/50 p-5">
                            <p class="break-all text-xs text-sky-300">{{ $assignment->trip->code }}</p>
                            <h3 class="mt-2 break-words text-lg font-semibold">{{ $assignment->trip->name }}</h3>
                            <p class="mt-2 text-sm text-slate-300">{{ $assignment->trip->customer->name }} · {{ $assignment->trip->destination }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ $assignment->trip->start_date->format('d M Y') }} — {{ $assignment->trip->end_date->format('d M Y') }}</p>

                            @if ($assignment->trip->status === \App\TripStatus::Confirmed)
                                <p class="mt-4 text-sm text-emerald-200">DP sudah tercatat. Anda dapat menerima tugas ini.</p>
                                <form method="POST" action="{{ route('tour-leader.assignments.accept', $assignment) }}" class="mt-3">
                                    @csrf
                                    <button class="min-h-11 rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950">Terima tugas</button>
                                </form>
                            @else
                                <p class="mt-4 rounded-lg bg-amber-400/10 p-3 text-sm text-amber-200">Menunggu DP customer. Persetujuan belum dapat dilakukan sampai status trip confirmed.</p>
                            @endif

                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm text-slate-300">Tidak dapat menerima tugas ini?</summary>
                                <form method="POST" action="{{ route('tour-leader.assignments.reject', $assignment) }}" class="mt-3 flex flex-col gap-3 sm:flex-row">
                                    @csrf @method('DELETE')
                                    <input name="response_note" required maxlength="1000" placeholder="Alasan penolakan (wajib)" class="min-w-0 flex-1 rounded-xl border border-white/20 bg-slate-900 px-4 py-3">
                                    <button class="rounded-xl border border-red-300/40 px-5 py-3 text-sm font-semibold text-red-200">Tolak tugas</button>
                                </form>
                            </details>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2 class="text-xl font-semibold">Tugas aktif</h2>
            <div class="mt-4 flex flex-col gap-4">
                @forelse ($trips as $trip)
                    <article class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
                        <p class="break-all text-xs text-sky-400">{{ $trip->code }}</p>
                        <h3 class="mt-2 break-words text-xl font-semibold">{{ $trip->name }}</h3>
                        <p class="mt-3 break-words text-sm text-slate-300">{{ $trip->customer->name }} · {{ $trip->destination }}</p>
                        <p class="mt-2 text-sm text-slate-300">{{ $trip->start_date->format('d M Y') }} — {{ $trip->end_date->format('d M Y') }}</p>
                        <p class="mt-2 text-sm text-sky-300">{{ $trip->status->label() }}</p>
                        <a href="{{ route('tour-leader.trips.show', $trip) }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Lihat detail</a>
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-white/20 p-7 text-slate-300">Belum ada tugas aktif. Undangan baru akan muncul di bagian atas halaman ini.</p>
                @endforelse
            </div>
            {{ $trips->links() }}
        </section>

        @if ($responses->isNotEmpty())
            <section class="rounded-2xl border border-white/10 p-5">
                <h2 class="font-semibold">Undangan yang Anda tolak</h2>
                <div class="mt-4 flex flex-col gap-3">
                    @foreach ($responses as $assignment)
                        <div class="rounded-xl bg-white/5 p-4">
                            <p class="font-medium">{{ $assignment->trip->name }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $assignment->responded_at->format('d M Y H:i') }}</p>
                            <p class="mt-2 break-words text-sm text-slate-300">Alasan: {{ $assignment->response_note }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
