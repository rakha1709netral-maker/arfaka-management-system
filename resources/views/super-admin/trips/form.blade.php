@extends('layouts.super-admin')
@section('title', 'Trip | ARFAKA TOUR')
@section('main')
<div class="mx-auto flex max-w-3xl flex-col gap-6">
<a href="{{ route('super-admin.trips.index') }}" class="text-emerald-400">&larr; Trips</a>
<h1 class="text-3xl font-semibold">{{ $trip->exists ? 'Edit Trip' : 'Tambah Trip' }}</h1>
@if ($customers->isEmpty())
<p class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">Tambahkan <a class="text-emerald-400 underline" href="{{ route('super-admin.customers.create') }}">customer</a> sebelum membuat trip.</p>
@endif
@if ($trip->tourLeaders->contains('is_active', false))
<p class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7 text-amber-300">Tour Leader sebelumnya sudah inactive. Pilih TL aktif atau kosongkan assignment saat menyimpan.</p>
@endif
<form method="POST" action="{{ $trip->exists ? route('super-admin.trips.update', $trip) : route('super-admin.trips.store') }}" class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7 flex flex-col gap-5">
@csrf
@if ($trip->exists) @method('PUT') @endif
<x-field name="name" label="Nama Trip" :value="$trip->name" :required="true" maxlength="255" />
<x-field name="customer_id" label="Customer" type="select" :required="true">
<option value="">Pilih customer</option>
@foreach ($customers as $customer)
<option value="{{ $customer->id }}" @selected((string) old('customer_id', $trip->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
@endforeach
</x-field>
<x-field name="destination" label="Destinasi" :value="$trip->destination" :required="true" maxlength="255" />
<div class="grid min-w-0 gap-5 sm:grid-cols-2">
<x-field name="start_date" label="Tanggal Mulai" type="date" :value="$trip->start_date?->format('Y-m-d')" :required="true" />
<x-field name="end_date" label="Tanggal Selesai" type="date" :value="$trip->end_date?->format('Y-m-d')" :required="true" />
<x-field name="participant_count" label="Jumlah Peserta" type="number" :value="$trip->participant_count" :required="true" min="1" max="2147483647" step="1" />
<div class="flex flex-col gap-2"><p class="text-sm font-medium">Status otomatis</p><p class="rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-sm text-slate-300">{{ $trip->status?->label() ?? 'Draft' }}</p></div>
</div>
<x-field name="tour_leader_id" label="Tour Leader" type="select">
<option value="">Belum ditugaskan</option>
@foreach ($tourLeaders as $leader)
<option value="{{ $leader->id }}" @selected((string) old('tour_leader_id', $trip->tourLeaders->first()?->id) === (string) $leader->id)>{{ $leader->name }}</option>
@endforeach
</x-field>
<p class="text-sm text-slate-400">TL menerima notifikasi di dashboardnya. Assignment baru aktif setelah TL menyetujui dan trip sudah menerima DP.</p>
<x-field name="notes" label="Catatan Trip" type="textarea" :value="$trip->notes" maxlength="10000" />
<button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 font-semibold hover:bg-emerald-500">Simpan Trip</button>
</form></div>
@endsection
