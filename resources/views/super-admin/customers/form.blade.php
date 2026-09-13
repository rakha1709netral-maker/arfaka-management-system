@extends('layouts.super-admin')
@section('title', 'Customer | ARFAKA TOUR')
@section('main')
<div class="mx-auto flex max-w-2xl flex-col gap-6">
<a href="{{ route('super-admin.customers.index') }}" class="text-emerald-400">&larr; Customers</a>
<h1 class="text-3xl font-semibold">{{ $customer->exists ? 'Edit Customer' : 'Tambah Customer' }}</h1>
<form method="POST" action="{{ $customer->exists ? route('super-admin.customers.update', $customer) : route('super-admin.customers.store') }}" class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7 flex flex-col gap-5">
@csrf
@if ($customer->exists) @method('PUT') @endif
<x-field name="name" label="Nama Customer" :value="$customer->name" :required="true" maxlength="255" />
<x-field name="pic_name" label="Nama PIC" :value="$customer->pic_name" maxlength="255" />
<x-field name="phone" label="Telepon" type="tel" :value="$customer->phone" maxlength="50" />
<x-field name="notes" label="Catatan Internal" type="textarea" :value="$customer->notes" maxlength="10000" />
<button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 font-semibold hover:bg-emerald-500">Simpan Customer</button>
</form></div>
@endsection
