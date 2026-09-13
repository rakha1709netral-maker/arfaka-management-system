@extends('layouts.super-admin')

@section('title', 'Tour Leaders | ARFAKA TOUR')

@section('main')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold text-emerald-400">Account Management</p>
                <h1 class="text-3xl font-semibold tracking-tight">Tour Leaders</h1>
                <p class="max-w-2xl text-sm text-slate-400">Kelola akun Tour Leader yang dapat digunakan kembali untuk operasional Arfaka Tour.</p>
            </div>

            <a href="{{ route('super-admin.tour-leaders.create') }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">
                Tambah Tour Leader
            </a>
        </div>

        @forelse ($tourLeaders as $tourLeader)
            <article class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-lg shadow-black/10">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="truncate text-lg font-semibold">{{ $tourLeader->name }}</h2>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $tourLeader->is_active ? 'bg-emerald-400/15 text-emerald-300' : 'bg-slate-400/15 text-slate-300' }}">
                                {{ $tourLeader->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <dl class="mt-3 grid gap-2 text-sm text-slate-400 sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="sr-only">Email</dt>
                                <dd class="truncate">{{ $tourLeader->email }}</dd>
                            </div>
                            <div>
                                <dt class="sr-only">Tanggal dibuat</dt>
                                <dd>Dibuat {{ $tourLeader->created_at->format('d M Y') }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 border-t border-white/10 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Penugasan trip</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($tourLeader->tripAssignments as $assignment)
                                    <a href="{{ route('super-admin.trips.show', $assignment->trip) }}" class="inline-flex max-w-full items-center gap-2 rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-xs transition hover:border-emerald-400/40">
                                        <span class="min-w-0"><span class="block truncate font-semibold text-slate-100">{{ $assignment->trip->name }}</span><span class="mt-0.5 block truncate text-slate-500">{{ $assignment->trip->code }} · {{ $assignment->trip->status->label() }}</span></span>
                                        <span class="shrink-0 {{ $assignment->status === \App\TripAssignmentStatus::Accepted ? 'text-emerald-300' : 'text-amber-300' }}">{{ $assignment->status->label() }}</span>
                                    </a>
                                @empty
                                    <span class="text-xs text-slate-500">Belum memiliki penugasan yang aktif atau menunggu persetujuan.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a href="{{ route('super-admin.tour-leaders.edit', $tourLeader) }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold transition hover:bg-white hover:text-slate-950">
                            Edit
                        </a>

                        @if ($tourLeader->is_active)
                            <form method="POST" action="{{ route('super-admin.tour-leaders.activation.destroy', $tourLeader) }}" onsubmit="return confirm('Yakin ingin menonaktifkan akun Tour Leader ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-xl border border-amber-400/40 px-4 py-2.5 text-sm font-semibold text-amber-300 transition hover:bg-amber-400 hover:text-slate-950 sm:w-auto">
                                    Deactivate
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('super-admin.tour-leaders.activation.store', $tourLeader) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 sm:w-auto">
                                    Activate
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/20 bg-white/5 px-6 py-12 text-center">
                <h2 class="text-lg font-semibold">Belum ada akun Tour Leader</h2>
                <p class="mt-2 text-sm text-slate-400">Buat akun pertama untuk mulai menyiapkan tim Tour Leader.</p>
            </div>
        @endforelse

        @if ($tourLeaders->hasPages())
            <div>{{ $tourLeaders->links() }}</div>
        @endif
    </div>
@endsection
