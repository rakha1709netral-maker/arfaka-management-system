@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-slate-950">
        <header class="border-b border-white/10 bg-slate-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>
                    <p class="text-xs font-semibold tracking-[0.28em] text-emerald-400">ARFAKA TOUR</p>
                    <p class="mt-1 text-sm text-slate-400">Super Admin</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <nav class="flex flex-wrap gap-2" aria-label="Navigasi super admin">
                        <a href="{{ route('super-admin.finance.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.finance.*') ? 'bg-white text-slate-950' : 'text-emerald-300 hover:bg-white/10' }}">Keuangan</a>
                        <a href="{{ route('super-admin.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.index') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            Home
                        </a>
                        <a href="{{ route('super-admin.tour-leaders.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.tour-leaders.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            Tour Leaders
                        </a>
                        <a href="{{ route('super-admin.customers.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.customers.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Customers</a>
                        <a href="{{ route('super-admin.trips.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.trips.*') ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Trips</a>
                    </nav>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold transition hover:bg-white hover:text-slate-950 sm:w-auto">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @yield('main')
        </main>
    </div>
@endsection
