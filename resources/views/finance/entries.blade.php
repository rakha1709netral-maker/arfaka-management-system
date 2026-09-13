<div class="flex flex-col gap-3">
    @forelse ($entries as $entry)
        <article class="rounded-2xl border border-white/10 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-slate-400">#{{ $entry->id }} · {{ $entry->occurred_on->format('d/m/Y') }} · {{ $entry->type->label() }}</p>
                    <h3 class="mt-2 break-words font-semibold">{{ $entry->description }}</h3>
                    <p class="mt-1 text-xs text-slate-400">{{ $entry->category?->label() }} · {{ $entry->leader?->name ?? 'Perusahaan' }} · Dicatat {{ $entry->creator->name }}</p>
                </div>
                <div class="text-right"><p class="text-lg font-semibold tabular-nums">Rp {{ number_format($entry->amount, 0, ',', '.') }}</p><span class="mt-1 inline-flex rounded-full border border-white/20 px-3 py-1 text-xs">{{ ['pending' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'void' => 'Dibatalkan'][$entry->status] }}</span></div>
            </div>
            @if ($entry->review_reason)
                <div class="mt-4 rounded-xl border {{ $entry->status === 'rejected' ? 'border-red-400/30 bg-red-400/10 text-red-100' : 'border-white/10 bg-white/5 text-slate-300' }} p-4 text-sm">
                    <p class="font-semibold">{{ $entry->status === 'rejected' ? 'Alasan pengajuan ditolak' : 'Catatan pemeriksaan' }}</p>
                    <p class="mt-1 break-words">{{ $entry->review_reason }}</p>
                    @if ($entry->reviewer || $entry->reviewed_at)
                        <p class="mt-2 text-xs opacity-70">{{ $entry->reviewer?->name }}{{ $entry->reviewed_at ? ' · '.$entry->reviewed_at->format('d/m/Y H:i') : '' }}</p>
                    @endif
                </div>
            @endif
            @if ($entry->captured_at)
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-400">
                    <span>Foto: {{ $entry->captured_at->format('d/m/Y H:i:s') }}</span>
                    <span>Lokasi: {{ $entry->latitude }}, {{ $entry->longitude }} (akurasi ±{{ number_format((float) $entry->location_accuracy, 0, ',', '.') }} m)</span>
                    @if ($entry->receipt_original_size && $entry->receipt_stored_size)
                        <span>Kompresi: {{ number_format($entry->receipt_original_size / 1024, 0, ',', '.') }} KB → {{ number_format($entry->receipt_stored_size / 1024, 0, ',', '.') }} KB</span>
                    @endif
                </div>
            @endif
            @if ($entry->receipt_path)
                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                    <a class="inline-flex min-h-11 items-center text-emerald-300" href="{{ route('entries.receipt', [$trip, $entry]) }}" target="_blank" rel="noopener">Lihat bukti privat &nearr;</a>
                    <a class="inline-flex min-h-11 items-center text-slate-300" href="{{ route('entries.receipt', ['trip' => $trip, 'entry' => $entry, 'download' => 1]) }}">Unduh &darr;</a>
                </div>
            @endif
            @if ($isAdmin && ! $trip->finances_locked_at && in_array($entry->status, ['pending', 'approved'], true))
                <details class="mt-3 print:hidden">
                    <summary class="cursor-pointer text-sm text-slate-300">{{ $entry->status === 'pending' ? 'Periksa pengajuan' : 'Koreksi / batalkan transaksi' }}</summary>
                    <form method="POST" action="{{ route('super-admin.trips.entries.update', [$trip, $entry]) }}" class="mt-3 grid gap-3 sm:grid-cols-3" data-confirm="Simpan keputusan ini ke histori keuangan?">
                        @csrf @method('PATCH')
                        <div><label for="decision-{{ $entry->id }}" class="text-xs text-slate-400">Keputusan</label><select id="decision-{{ $entry->id }}" name="status" class="mt-1 w-full rounded-xl border border-white/20 bg-slate-900 p-3">@if ($entry->status === 'pending')<option value="approved">Setujui</option><option value="rejected">Tolak</option>@else<option value="void">Batalkan (void)</option>@endif</select></div>
                        <div><label for="reason-{{ $entry->id }}" class="text-xs text-slate-400">Alasan wajib</label><input id="reason-{{ $entry->id }}" name="reason" required maxlength="1000" class="mt-1 w-full rounded-xl border border-white/20 bg-slate-900 p-3"></div>
                        <button class="self-end rounded-xl border border-white/20 px-4 py-3 text-sm font-semibold">Simpan keputusan</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-400">Nominal tidak ditimpa. Jika salah, batalkan dengan alasan lalu buat transaksi pengganti.</p>
                </details>
            @endif
        </article>
    @empty
        <p class="rounded-2xl border border-dashed border-white/20 p-8 text-center text-slate-400">Belum ada transaksi. Catatan keuangan akan tampil di sini.</p>
    @endforelse
</div>
{{ $entries->links() }}
