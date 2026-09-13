@extends('layouts.super-admin')
@section('title', 'Customers | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-6">
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><h1 class="text-3xl font-semibold">Customers</h1><a href="{{ route('super-admin.customers.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 font-semibold hover:bg-emerald-500">Tambah Customer</a></div>
<form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
<div class="min-w-0 flex-1"><x-field name="search" label="Cari nama customer" :value="$search" maxlength="255" /></div>
<button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Cari</button><a href="{{ route('super-admin.customers.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Reset</a>
</form>
@forelse ($customers as $customer)
<article class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
<div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
<div class="min-w-0"><h2 class="break-words text-lg font-semibold">{{ $customer->name }}</h2><p class="mt-2 break-words text-sm text-slate-400">PIC: {{ $customer->pic_name ?: '—' }}</p><p class="break-words text-sm text-slate-400">Telepon: {{ $customer->phone ?: '—' }}</p></div>
<a href="{{ route('super-admin.customers.edit', $customer) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10 shrink-0 self-start">Edit Customer</a>
</div></article>
@empty
<p class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7 text-slate-300">{{ $search !== '' ? 'Customer tidak ditemukan.' : 'Belum ada customer. Tambahkan customer pertama.' }}</p>
@endforelse
{{ $customers->links() }}
</div>
@endsection
