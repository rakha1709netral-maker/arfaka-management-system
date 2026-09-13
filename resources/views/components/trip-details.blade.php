@props(['trip'])
<section class="min-w-0 rounded-2xl border border-white/10 bg-white/5 p-5 sm:p-7">
<dl class="grid gap-5 sm:grid-cols-2">
@foreach ([
'Kode Trip' => $trip->code,
'Customer' => $trip->customer->name,
'Destinasi' => $trip->destination,
'Tanggal' => $trip->start_date->format('d M Y').' — '.$trip->end_date->format('d M Y'),
'Jumlah Peserta' => $trip->participant_count,
'Status' => $trip->status->label(),
'Tour Leader' => $trip->tourLeaders->map(fn ($leader) => $leader->name.' ('.\App\TripAssignmentStatus::from($leader->pivot->status)->label().')')->join(', ') ?: 'Belum ditugaskan',
] as $label => $value)
<div class="min-w-0"><dt class="text-sm text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words">{{ $value }}</dd></div>
@endforeach
<div class="min-w-0 sm:col-span-2"><dt class="text-sm text-slate-400">Catatan Trip</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $trip->notes ?: 'Tidak ada catatan.' }}</dd></div>
</dl>
</section>
