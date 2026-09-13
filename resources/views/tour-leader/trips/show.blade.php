@extends('layouts.tour-leader')
@section('title', 'Detail Trip | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-6">
<a href="{{ route('tour-leader.index') }}" class="text-sky-400">&larr; Trip Saya</a>
<h1 class="break-words text-3xl font-semibold">{{ $trip->name }}</h1>
<p class="text-sm text-slate-400">Informasi trip hanya dapat dilihat. Hubungi administrator untuk perubahan.</p>
<x-trip-details :trip="$trip" />
<a href="{{ route('tour-leader.trips.finance', $trip) }}" class="self-start rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950">Pengeluaran & saldo trip</a>
</div>
@endsection
