<div class="flex flex-col gap-4">
    @forelse ($entries as $entry)
        @php
            $status = match ($entry->status) {
                'approved' => ['label' => 'Disetujui', 'border' => 'border-emerald-400/40', 'badge' => 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100', 'meaning' => 'Tercatat dalam perhitungan keuangan trip.'],
                'rejected' => ['label' => 'Ditolak', 'border' => 'border-rose-400/40', 'badge' => 'border-rose-400/40 bg-rose-400/10 text-rose-100', 'meaning' => 'Tidak masuk perhitungan keuangan trip.'],
                'void' => ['label' => 'Dibatalkan', 'border' => 'border-slate-400/40', 'badge' => 'border-slate-400/40 bg-slate-400/10 text-slate-200', 'meaning' => 'Transaksi dibatalkan dan tidak masuk perhitungan.'],
                default => ['label' => 'Menunggu pemeriksaan', 'border' => 'border-amber-400/40', 'badge' => 'border-amber-400/40 bg-amber-400/10 text-amber-100', 'meaning' => 'Belum masuk perhitungan sampai disetujui admin.'],
            };
        @endphp
        <article class="rounded-2xl border bg-slate-900/70 p-5 sm:p-6 {{ $status['border'] }}">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-slate-400">Transaksi #{{ $entry->id }} · {{ $entry->occurred_on->format('d M Y') }}</p>
                    <h3 class="mt-2 break-words text-lg font-semibold leading-snug">{{ $entry->description }}</h3>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-300">
                        <span class="rounded-lg bg-white/10 px-2.5 py-1">{{ $entry->type->label() }}</span>
                        @if ($entry->category)<span class="rounded-lg bg-white/10 px-2.5 py-1">{{ $entry->category->label() }}</span>@endif
                        <span class="rounded-lg bg-white/10 px-2.5 py-1">{{ $entry->leader?->name ?? 'Perusahaan' }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end sm:text-right">
                    <p class="text-xl font-semibold tabular-nums">Rp {{ number_format($entry->amount, 0, ',', '.') }}</p>
                    <span class="inline-flex rounded-full border px-3 py-1.5 text-xs font-semibold {{ $status['badge'] }}">{{ $status['label'] }}</span>
                </div>
            </div>
            <p class="mt-4 border-t border-white/10 pt-3 text-sm {{ $entry->status === 'approved' ? 'text-emerald-200' : ($entry->status === 'pending' ? 'text-amber-200' : 'text-slate-300') }}">{{ $status['meaning'] }}</p>
            <p class="mt-2 text-xs text-slate-400">Dicatat oleh {{ $entry->creator->name }}{{ $entry->reviewed_at ? ' · Diperiksa '.$entry->reviewed_at->format('d M Y, H:i') : '' }}</p>

            @if ($entry->review_reason)
                <div class="mt-4 rounded-xl border {{ $entry->status === 'rejected' ? 'border-rose-400/30 bg-rose-400/10 text-rose-100' : 'border-white/10 bg-white/5 text-slate-200' }} p-4 text-sm">
                    <p class="font-semibold">{{ $entry->status === 'rejected' ? 'Alasan pengajuan ditolak' : ($entry->status === 'void' ? 'Alasan transaksi dibatalkan' : 'Catatan pemeriksaan') }}</p>
                    <p class="mt-1 break-words leading-relaxed">{{ $entry->review_reason }}</p>
                    @if ($entry->reviewer)<p class="mt-2 text-xs opacity-75">Diperiksa oleh {{ $entry->reviewer->name }}</p>@endif
                </div>
            @endif
            @if ($entry->captured_at)
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-400">
                    <span>Foto: {{ $entry->captured_at->format('d/m/Y H:i:s') }}</span>
                    <span>Lokasi: {{ $entry->latitude }}, {{ $entry->longitude }} (akurasi ±{{ number_format((float) $entry->location_accuracy, 0, ',', '.') }} m)</span>
                    @if ($entry->receipt_original_size && $entry->receipt_stored_size)
                        <span>Kompresi: {{ number_format($entry->receipt_original_size / 1024, 0, ',', '.') }} KB → {{ number_format($entry->receipt_stored_size / 1024, 0, ',', '.') }} KB</span>
                    @endif
                </div>
            @endif
            @if ($entry->receipt_path)
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                    <a class="inline-flex min-h-10 items-center rounded-lg border border-emerald-400/30 px-3 py-2 font-medium text-emerald-200 hover:bg-emerald-400/10" href="{{ route('entries.receipt', [$trip, $entry]) }}" target="_blank" rel="noopener">Lihat bukti privat ↗</a>
                    <a class="inline-flex min-h-10 items-center rounded-lg border border-white/20 px-3 py-2 text-slate-300 hover:bg-white/10" href="{{ route('entries.receipt', ['trip' => $trip, 'entry' => $entry, 'download' => 1]) }}">Unduh bukti ↓</a>
                </div>
            @endif
            @if ($isAdmin && ! $trip->finances_locked_at && in_array($entry->status, ['pending', 'approved'], true))
                <details class="mt-4 rounded-xl border border-white/10 bg-slate-950/50 p-4 print:hidden">
                    <summary class="cursor-pointer text-sm font-semibold text-sky-200">{{ $entry->status === 'pending' ? 'Periksa pengajuan ini' : 'Koreksi / batalkan transaksi' }}</summary>
                    <form method="POST" action="{{ route('super-admin.trips.entries.update', [$trip, $entry]) }}" class="mt-4 grid gap-3 sm:grid-cols-3" data-confirm="Simpan keputusan ini ke histori keuangan?">
                        @csrf @method('PATCH')
                        <div><label for="decision-{{ $entry->id }}" class="text-xs text-slate-400">Keputusan</label><select id="decision-{{ $entry->id }}" name="status" class="mt-1 w-full rounded-xl border border-white/20 bg-slate-900 p-3">@if ($entry->status === 'pending')<option value="approved">Setujui</option><option value="rejected">Tolak</option>@else<option value="void">Batalkan (void)</option>@endif</select></div>
                        <div><label for="reason-{{ $entry->id }}" class="text-xs text-slate-400">Alasan wajib</label><input id="reason-{{ $entry->id }}" name="reason" required maxlength="1000" class="mt-1 w-full rounded-xl border border-white/20 bg-slate-900 p-3"></div>
                        <button class="self-end rounded-xl border border-white/20 px-4 py-3 text-sm font-semibold hover:bg-white/10">Simpan keputusan</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-400">Nominal tidak ditimpa. Jika salah, batalkan dengan alasan lalu buat transaksi pengganti.</p>
                </details>
            @endif
        </article>
    @empty
        <p class="rounded-2xl border border-dashed border-white/20 p-8 text-center text-slate-400">{{ $entryStatus ? 'Tidak ada transaksi dengan status ini.' : 'Belum ada transaksi. Catatan keuangan akan tampil di sini.' }}</p>
    @endforelse
</div>
{{ $entries->links() }}
