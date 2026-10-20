@extends(auth()->user()->role === \App\UserRole::SuperAdmin ? 'layouts.super-admin' : 'layouts.tour-leader')

@section('title', 'Akun Saya | ARFAKA TOUR')

@section('main')
    <div class="mx-auto max-w-xl">
        <p class="text-sm font-semibold text-emerald-400">Akun Saya</p>
        <h1 class="mt-2 text-3xl font-semibold">Ubah Password</h1>
        <p class="mt-2 text-sm text-slate-400">Gunakan password baru minimal 8 karakter. Password saat ini tidak pernah ditampilkan.</p>

        @if (session('status'))
            <p class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('my-password.update') }}" class="mt-6 flex flex-col gap-5 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-2">
                <label for="current_password" class="text-sm font-medium">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                @error('current_password') <p class="text-sm text-red-400" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium">Password Baru</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" minlength="8" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                @error('password') <p class="text-sm text-red-400" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Konfirmasi Password Baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" minlength="8" class="w-full rounded-xl border border-white/15 bg-slate-900 px-4 py-3 text-sm text-white outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-500">Simpan Password</button>
        </form>
    </div>
@endsection
