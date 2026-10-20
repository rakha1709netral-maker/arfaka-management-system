@extends('layouts.app')

@section('title', 'Reset Password | ARFAKA TOUR')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-3xl bg-white p-6 text-slate-900 shadow-2xl sm:p-9">
            <p class="text-xs font-semibold tracking-[0.28em] text-emerald-700">ARFAKA TOUR</p>
            <h1 class="mt-3 text-2xl font-semibold">Reset Password</h1>
            <p class="mt-2 text-sm text-slate-600">Buat password baru untuk akun Anda.</p>

            <form method="POST" action="{{ route('password.update') }}" class="mt-7 flex flex-col gap-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" readonly autocomplete="email" class="w-full rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm">
                    @error('email') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium">Password Baru</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
                    @error('password') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="password_confirmation" class="text-sm font-medium">Konfirmasi Password Baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="8" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Simpan Password</button>
            </form>
        </section>
    </main>
@endsection
