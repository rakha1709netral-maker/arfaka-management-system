@extends('layouts.app')
@section('content')
<header class="border-b border-slate-200 bg-slate-100">
<div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6">
<div class="flex items-center gap-3"><img src="{{ asset('images/arfaka-logo.png') }}" alt="Logo Arfaka Tour and Travel" class="h-16 w-36 shrink-0 object-contain"><p class="text-sm font-medium text-slate-600">Tour Leader Area</p></div>
<nav class="flex flex-wrap items-center gap-3" aria-label="Navigasi Tour Leader">
<a href="{{ route('tour-leader.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200">Trip Saya</a>
<a href="{{ route('my-password.edit') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200">Akun Saya</a>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200" type="submit">Logout</button></form>
</nav></div>
</header>
<main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">@yield('main')</main>
@endsection
