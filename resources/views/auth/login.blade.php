@extends('layouts.app')

@section('title', 'Login | ARFAKA TOUR')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-3xl border border-white/10 bg-white p-6 text-slate-900 shadow-2xl shadow-black/30 sm:p-9">
            <div class="flex flex-col gap-2 text-center">
                <p class="text-xs font-semibold tracking-[0.28em] text-emerald-700">INTERNAL SYSTEM</p>
                <h1 class="text-3xl font-semibold tracking-tight">ARFAKA TOUR</h1>
                <p class="text-sm text-slate-500">Masuk menggunakan akun internal Anda.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 flex flex-col gap-5">
                @csrf

                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium text-slate-700">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" placeholder="nama@arfaka.test">
                    @error('email')
                        <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100" placeholder="Masukkan password">
                    @error('password')
                        <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                    Login
                </button>
            </form>
        </section>
    </main>
@endsection
