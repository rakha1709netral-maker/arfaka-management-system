@extends('layouts.super-admin')

@section('title', 'Edit Tour Leader | ARFAKA TOUR')

@section('main')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <a href="{{ route('super-admin.tour-leaders.index') }}" class="text-sm font-medium text-emerald-400 hover:text-emerald-300">&larr; Kembali ke Tour Leaders</a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-semibold tracking-tight">Edit {{ $tourLeader->name }}</h1>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $tourLeader->is_active ? 'bg-emerald-400/15 text-emerald-300' : 'bg-slate-400/15 text-slate-300' }}">
                    {{ $tourLeader->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('super-admin.tour-leaders.update', $tourLeader) }}" class="flex min-w-0 flex-col gap-5 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
                @csrf
                @method('PUT')

                <div>
                    <h2 class="text-xl font-semibold">Profil</h2>
                    <p class="mt-1 text-sm text-slate-400">Ubah nama dan alamat email akun.</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="name" class="text-sm font-medium">Nama</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $tourLeader->name) }}" required autocomplete="name" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                    @error('name')
                        <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $tourLeader->email) }}" required autocomplete="email" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                    @error('email')
                        <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">Simpan Profil</button>
            </form>

            <div class="flex min-w-0 flex-col gap-6">
                <form method="POST" action="{{ route('super-admin.tour-leaders.password.update', $tourLeader) }}" class="flex flex-col gap-5 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
                    @csrf
                    @method('PUT')

                    <div>
                        <h2 class="text-xl font-semibold">Ganti Password</h2>
                        <p class="mt-1 text-sm text-slate-400">Password lama tidak pernah ditampilkan.</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password" class="text-sm font-medium">Password Baru</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                        @error('password')
                            <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation" class="text-sm font-medium">Konfirmasi Password Baru</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                    </div>

                    <button type="submit" class="rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold transition hover:bg-white hover:text-slate-950">Perbarui Password</button>
                </form>

                <section class="rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
                    <h2 class="text-xl font-semibold">Status Akun</h2>
                    <p class="mt-1 text-sm text-slate-400">Akun inactive tidak dapat login atau memakai session yang masih aktif.</p>

                    <div class="mt-5">
                        @if ($tourLeader->is_active)
                            <form method="POST" action="{{ route('super-admin.tour-leaders.activation.destroy', $tourLeader) }}" onsubmit="return confirm('Yakin ingin menonaktifkan akun Tour Leader ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-xl border border-amber-400/40 px-5 py-3 text-sm font-semibold text-amber-300 transition hover:bg-amber-400 hover:text-slate-950">Deactivate Akun</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('super-admin.tour-leaders.activation.store', $tourLeader) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500">Activate Akun</button>
                            </form>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
