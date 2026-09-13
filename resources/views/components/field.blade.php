@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false])
<div class="flex min-w-0 flex-col gap-2">
<label for="{{ $name }}" class="text-sm font-medium">{{ $label }}{{ $required ? ' *' : '' }}</label>
@if ($type === 'textarea')
<textarea id="{{ $name }}" name="{{ $name }}" rows="4" {{ $attributes->class('w-full min-w-0 rounded-xl border border-white/20 bg-slate-900 px-4 py-3 text-base focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30') }}>{{ old($name, $value) }}</textarea>
@elseif ($type === 'select')
<select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->class('w-full min-w-0 rounded-xl border border-white/20 bg-slate-900 px-4 py-3 text-base focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30') }}>{{ $slot }}</select>
@else
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) {{ $attributes->class('w-full min-w-0 rounded-xl border border-white/20 bg-slate-900 px-4 py-3 text-base focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30') }}>
@endif
@error($name)<p class="text-sm text-red-300" role="alert">{{ $message }}</p>@enderror
</div>
