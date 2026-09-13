@extends('layouts.super-admin')
@section('title', 'Detail Trip | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-6">
<a href="{{ route('super-admin.trips.index') }}" class="text-sky-400">&larr; Trips</a>
<h1 class="break-words text-3xl font-semibold">{{ $trip->name }}</h1>
<a href="{{ route('super-admin.trips.edit', $trip) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10 self-start">Edit Trip</a>
<x-trip-details :trip="$trip" />
<a href="{{ route('super-admin.trips.finance.show', $trip) }}" class="self-start rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950">Kelola keuangan & RAB</a>
</div>
@endsection
