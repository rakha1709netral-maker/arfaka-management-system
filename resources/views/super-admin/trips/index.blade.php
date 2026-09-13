@extends('layouts.super-admin')
@section('title', 'Trips | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-5">
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><h1 class="text-3xl font-semibold">Trips</h1>
<a href="{{ route('super-admin.trips.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 font-semibold hover:bg-emerald-500">Tambah Trip</a></div>
@forelse ($trips as $trip)
<article class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
<p class="break-all text-xs text-sky-400">{{ $trip->code }}</p>
<h2 class="mt-2 break-words text-xl font-semibold">{{ $trip->name }}</h2>
<p class="mt-3 break-words text-sm text-slate-300">{{ $trip->customer->name }} · {{ $trip->destination }}</p>
<p class="mt-2 text-sm text-slate-300">{{ $trip->start_date->format('d M Y') }} — {{ $trip->end_date->format('d M Y') }}</p>
<p class="mt-2 text-sm text-sky-300">{{ $trip->status->label() }}</p>
<p class="mt-2 break-words text-sm">TL: {{ $trip->tourLeaders->pluck('name')->join(', ') ?: 'Belum ditugaskan' }}</p>
<div class="mt-5 flex flex-col gap-3 sm:flex-row">
<a href="{{ route('super-admin.trips.show', $trip) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Lihat Detail</a>
<a href="{{ route('super-admin.trips.edit', $trip) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Edit Trip</a>
</div></article>
@empty
<p class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7 text-slate-300">Belum ada trip. Buat trip pertama untuk customer Anda.</p>
@endforelse
{{ $trips->links() }}
</div>
@endsection
