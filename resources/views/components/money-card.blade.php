@props(['label', 'amount', 'hint' => ''])
<div {{ $attributes->class('min-w-0 rounded-2xl border border-white/10 bg-slate-900 p-5') }}>
    <p class="text-sm text-slate-400">{{ $label }}</p>
    <p class="mt-2 break-words text-2xl font-semibold tabular-nums">{{ $amount === null ? 'Belum diisi' : 'Rp '.number_format($amount, 0, ',', '.') }}</p>
    @if ($hint)<p class="mt-2 text-xs leading-relaxed text-slate-400">{{ $hint }}</p>@endif
</div>
