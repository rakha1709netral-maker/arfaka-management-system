@extends('layouts.app')

@section('title', 'Lupa Password | ARFAKA TOUR')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-3xl bg-white p-6 text-slate-900 shadow-2xl sm:p-9">
            <p class="text-xs font-semibold tracking-[0.28em] text-emerald-700">ARFAKA TOUR</p>
            <h1 class="mt-3 text-2xl font-semibold">Lupa Password</h1>
            <p class="mt-2 text-sm text-slate-600">Masukkan email akun Anda. Jika memenuhi syarat, kami akan mengirim tautan reset password.</p>

            @if (session('status'))
                <p class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-7 flex flex-col gap-5">
                @csrf
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
                    @error('email') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800">Kirim Link Reset</button>
                <a href="{{ route('login') }}" class="text-center text-sm font-medium text-emerald-700 hover:underline">Kembali ke Login</a>
            </form>
        </section>
    </main>
@endsection
