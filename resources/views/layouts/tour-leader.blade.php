@extends('layouts.app')
@section('content')
<header class="border-b border-white/10 bg-slate-900">
<div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6">
<div><p class="text-xs font-semibold tracking-widest text-sky-400">ARFAKA TOUR</p><p class="text-sm text-slate-400">Tour Leader Area</p></div>
<nav class="flex flex-wrap items-center gap-3" aria-label="Navigasi Tour Leader">
<a href="{{ route('tour-leader.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10">Trip Saya</a>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold hover:bg-white/10" type="submit">Logout</button></form>
</nav></div>
</header>
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">@yield('main')</main>
@endsection
