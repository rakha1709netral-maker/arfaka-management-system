@extends('layouts.super-admin')

@section('title', 'Tambah Tour Leader | ARFAKA TOUR')

@section('main')
    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex flex-col gap-2">
            <a href="{{ route('super-admin.tour-leaders.index') }}" class="text-sm font-medium text-emerald-400 hover:text-emerald-300">&larr; Kembali ke Tour Leaders</a>
            <h1 class="text-3xl font-semibold tracking-tight">Tambah Tour Leader</h1>
            <p class="text-sm text-slate-400">Akun baru otomatis aktif. Tour Leader akan menerima email untuk membuat password sendiri.</p>
        </div>

        <form method="POST" action="{{ route('super-admin.tour-leaders.store') }}" class="flex flex-col gap-5 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="name" class="text-sm font-medium">Nama</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                @error('name')
                    <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                @error('email')
                    <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                <a href="{{ route('super-admin.tour-leaders.index') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold transition hover:bg-white hover:text-slate-950">Batal</a>
                <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">Simpan Tour Leader</button>
            </div>
        </form>
    </div>
@endsection
