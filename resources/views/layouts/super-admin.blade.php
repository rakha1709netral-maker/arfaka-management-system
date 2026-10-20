@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-slate-950">
        <header class="border-b border-slate-200 bg-slate-100">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/arfaka-logo.png') }}" alt="Logo Arfaka Tour and Travel" class="h-16 w-36 shrink-0 object-contain">
                    <p class="text-sm font-medium text-slate-600">Super Admin</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <nav class="flex flex-wrap gap-2" aria-label="Navigasi super admin">
                        <a href="{{ route('super-admin.finance.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.finance.*') ? 'bg-slate-900 text-white' : 'text-emerald-800 hover:bg-slate-200' }}">Keuangan</a>
                        <a href="{{ route('super-admin.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.index') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-200' }}">
                            Home
                        </a>
                        <a href="{{ route('super-admin.tour-leaders.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.tour-leaders.*') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-200' }}">
                            Tour Leaders
                        </a>
                        <a href="{{ route('super-admin.customers.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.customers.*') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-200' }}">Customers</a>
                        <a href="{{ route('super-admin.trips.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('super-admin.trips.*') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-200' }}">Trips</a>
                        <a href="{{ route('my-password.edit') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('my-password.*') ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-200' }}">Akun Saya</a>
                    </nav>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-900 hover:text-white sm:w-auto">
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

            @if (session('error'))
                <div class="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @yield('main')
        </main>
    </div>
@endsection
