@extends($isAdmin ? 'layouts.super-admin' : 'layouts.tour-leader')
@section('title', 'Keuangan Trip | ARFAKA TOUR')
@section('main')
<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4 rounded-3xl border border-white/10 bg-slate-900/70 p-5 sm:p-7">
        <div>
            <a class="text-sm text-emerald-400" href="{{ route($isAdmin ? 'super-admin.trips.show' : 'tour-leader.trips.show', $trip) }}">&larr; Detail trip</a>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-sky-300">Keuangan trip</p>
            <h1 class="mt-2 break-words text-3xl font-semibold">{{ $trip->name }}</h1>
            <p class="mt-2 break-words text-sm text-slate-400">{{ $trip->code }} · {{ $trip->customer->name }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $trip->start_date->format('d M Y') }} – {{ $trip->end_date->format('d M Y') }} · {{ $trip->destination }}</p>
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
            <span class="rounded-full border {{ $trip->finances_locked_at ? 'border-amber-400/40 bg-amber-400/10 text-amber-100' : 'border-emerald-400/40 bg-emerald-400/10 text-emerald-100' }} px-4 py-2 text-sm font-medium">{{ $trip->finances_locked_at ? 'Settlement selesai · Terkunci' : 'Pencatatan terbuka' }}</span>
            <span class="text-xs text-slate-400">Status perjalanan: {{ $trip->status->label() }}</span>
        </div>
    </div>
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-400/30 bg-red-400/10 p-4 text-sm text-red-200"><p class="font-semibold">Periksa kembali isian:</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (! $isAdmin && session('status'))<p role="status" class="rounded-xl bg-emerald-400/10 p-4 text-emerald-200">{{ session('status') }}</p>@endif

    @if ($isAdmin)
        <nav class="flex flex-wrap gap-3 text-sm text-emerald-300 print:hidden" aria-label="Keuangan trip"><a href="#transactions">Transaksi</a><a href="#budget">RAB</a><a href="#settlement">Settlement</a><a href="#history">Histori</a></nav>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-money-card label="Nilai kontrak" :amount="$trip->contract_amount" />
            <x-money-card label="Pembayaran customer" :amount="$summary['payment']" />
            <x-money-card label="Sisa tagihan" :amount="$summary['receivable']" />
            <x-money-card label="Rencana anggaran (RAB)" :amount="$summary['budget']" />
            <x-money-card label="Biaya disetujui" :amount="$summary['expense']" :hint="'Pending: Rp '.number_format($summary['pending'], 0, ',', '.')" />
            <x-money-card label="Margin kontrak sementara" :amount="$summary['profit']" hint="Kontrak − biaya disetujui, bukan saldo kas" />
        </div>
        @if (! $trip->finances_locked_at)
            <details class="rounded-2xl border border-white/10 bg-slate-900 p-5 print:hidden" @if ($trip->contract_amount === null) open @endif>
                <summary class="cursor-pointer font-semibold">Atur nilai kontrak</summary>
                <form method="POST" action="{{ route('super-admin.trips.finance.update', $trip) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <x-field name="contract_amount" label="Nilai kontrak (Rp)" type="number" :value="$trip->contract_amount" min="0" max="1000000000000" step="1" required />
                    <x-field name="reason" label="Catatan / alasan perubahan kontrak" maxlength="1000" required />
                    <button class="justify-self-start rounded-xl bg-white px-5 py-3 font-semibold text-slate-950">Simpan kontrak</button>
                </form>
            </details>
        @endif
    @else
        <p class="text-sm leading-relaxed text-slate-400">Catat pengeluaran trip dan lampirkan bukti. Admin memeriksa pengeluaran sebelum masuk perhitungan saldo. Nilai kontrak dan pembayaran customer hanya tersedia untuk admin.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-money-card label="Uang diterima dari perusahaan" :amount="$summary['advance'] + $summary['reimbursement']" />
            <x-money-card label="Pengeluaran disetujui" :amount="$summary['expense']" :hint="'Pending: Rp '.number_format($summary['pending'], 0, ',', '.')" />
            @php($balance = $summary['balances'][auth()->id()] ?? 0)
            <x-money-card :label="$balance < 0 ? 'Perlu diganti perusahaan' : 'Saldo perlu dikembalikan'" :amount="abs($balance)" hint="Saldo sudah memperhitungkan pengembalian dan reimbursement" />
        </div>
    @endif

    <section id="transactions" class="flex flex-col gap-4">
        <div>
            <h2 class="text-xl font-semibold">{{ $isAdmin ? 'Transaksi trip' : 'Pengeluaran & saldo saya' }}</h2>
            <p class="mt-1 text-sm text-slate-400">Pilih status untuk melihat transaksi yang perlu ditindaklanjuti atau sudah selesai.</p>
        </div>
        @if (! $trip->finances_locked_at && ($isAdmin || $trip->status === \App\TripStatus::Ongoing))
            <details class="rounded-2xl border border-white/10 bg-slate-900 p-5 print:hidden" @if (! $isAdmin) open @endif>
                <summary class="cursor-pointer font-semibold text-emerald-300">+ {{ $isAdmin ? 'Catat transaksi' : 'Ajukan pengeluaran' }}</summary>
                <form method="POST" enctype="multipart/form-data" action="{{ route($isAdmin ? 'super-admin.trips.entries.store' : 'tour-leader.trips.expenses.store', $trip) }}" class="mt-5 grid gap-4 sm:grid-cols-2" @if (! $isAdmin) data-camera-expense @endif>
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Illuminate\Support\Str::uuid()) }}">
                    @if (! $isAdmin)
                        <input type="hidden" name="captured_at" value="{{ old('captured_at') }}" data-captured-at>
                        <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-latitude>
                        <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-longitude>
                        <input type="hidden" name="location_accuracy" value="{{ old('location_accuracy') }}" data-location-accuracy>
                        <input type="hidden" name="receipt_original_size" value="{{ old('receipt_original_size') }}" data-original-size>
                    @endif
                    <x-field name="type" label="Jenis transaksi" type="select" required>
                        @foreach ($types as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>@endforeach
                    </x-field>
                    <x-field name="occurred_on" label="Tanggal transaksi" type="date" :value="now()->format('Y-m-d')" :max="now()->format('Y-m-d')" required />
                    <x-field name="amount" label="Nominal (Rp, tanpa titik/koma)" type="number" min="1" max="1000000000000" step="1" inputmode="numeric" required />
                    <x-field name="category" label="Kategori (hanya untuk pengeluaran)" type="select">
                        @foreach ($categories as $category)<option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>@endforeach
                    </x-field>
                    @if ($isAdmin)
                        <x-field name="leader_id" label="TL terkait / pembayar biaya" type="select">
                            <option value="">Perusahaan langsung / pembayaran customer</option>
                            @foreach ($leaders as $leader)<option value="{{ $leader->id }}" @selected((string) old('leader_id') === (string) $leader->id)>{{ $leader->name }}{{ ! $leader->is_active ? ' (nonaktif)' : '' }}</option>@endforeach
                        </x-field>
                        <p class="self-center text-xs leading-relaxed text-slate-400">Pilih TL untuk uang muka, pengembalian, reimbursement, atau biaya yang dibayar TL. Untuk biaya langsung perusahaan, kosongkan TL. TL lama/nonaktif hanya dapat diselesaikan saldonya.</p>
                    @endif
                    <div class="sm:col-span-2"><x-field name="description" label="Keterangan (mis. DP customer / makan siang / komisi TL)" maxlength="500" required /></div>
                    <div class="flex flex-col gap-2 sm:col-span-2">
                        <label for="receipt" class="text-sm font-medium">Bukti transaksi {{ $isAdmin ? '(opsional)' : '(wajib)' }}</label>
                        @if ($isAdmin)
                            <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full rounded-xl border border-white/20 p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-white">
                            <p class="text-xs text-slate-400">JPG/PNG/WebP atau PDF, maksimal 5 MB. Bukti tersimpan privat.</p>
                        @else
                            <input type="hidden" name="receipt_data" data-receipt-data>
                            <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-950 p-4" data-camera-widget>
                                <video class="hidden max-h-[70vh] w-full rounded-xl bg-black object-contain" autoplay muted playsinline data-camera-video></video>
                                <img class="hidden max-h-[70vh] w-full rounded-xl bg-black object-contain" alt="Pratinjau bukti pengeluaran" data-camera-preview>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-xl bg-sky-400 px-4 py-2.5 text-sm font-semibold text-slate-950" data-camera-start>Buka kamera</button>
                                    <button type="button" class="hidden rounded-xl bg-emerald-400 px-4 py-2.5 text-sm font-semibold text-slate-950" data-camera-capture>Ambil foto</button>
                                    <button type="button" class="hidden rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold" data-camera-retake>Foto ulang</button>
                                </div>
                                <p class="text-xs leading-relaxed text-slate-400">Kamera belakang diprioritaskan di HP dan webcam digunakan di laptop. Foto diberi timestamp/lokasi, dinormalisasi menjadi JPEG, lalu dikompres maksimal 1.600 px.</p>
                                <p class="hidden rounded-lg border p-3 text-xs" role="status" data-camera-status></p>
                            </div>
                        @endif
                    </div>
                    <button class="justify-self-start rounded-xl bg-emerald-400 px-5 py-3 font-semibold text-slate-950 hover:bg-emerald-300" data-submit-button>Simpan {{ $isAdmin ? 'transaksi' : 'pengajuan' }}</button>
                </form>
            </details>
        @elseif (! $isAdmin && ! $trip->finances_locked_at)
            <div class="rounded-2xl border border-amber-400/30 bg-amber-400/10 p-5 text-sm text-amber-100">
                Pengajuan pengeluaran belum tersedia. Fitur ini terbuka setelah DP customer tercatat, tugas Anda disetujui, dan status trip menjadi ongoing.
            </div>
        @endif
        <nav class="flex flex-wrap gap-2" aria-label="Filter status transaksi">
            @foreach ([null => ['Semua', 'border-white/20 text-slate-200'], 'pending' => ['Menunggu', 'border-amber-400/40 text-amber-200'], 'approved' => ['Disetujui', 'border-emerald-400/40 text-emerald-200'], 'rejected' => ['Ditolak', 'border-rose-400/40 text-rose-200'], 'void' => ['Dibatalkan', 'border-slate-400/40 text-slate-300']] as $status => [$label, $colors])
                @php($filterStatus = $status === '' ? null : $status)
                <a href="{{ route($isAdmin ? 'super-admin.trips.finance.show' : 'tour-leader.trips.finance', ['trip' => $trip, ...($filterStatus ? ['entry_status' => $filterStatus] : [])]) }}#transactions"
                   aria-current="{{ $entryStatus === $filterStatus ? 'page' : 'false' }}"
                   class="inline-flex min-h-10 items-center gap-2 rounded-full border px-3 py-2 text-xs font-medium transition hover:bg-white/10 {{ $colors }} {{ $entryStatus === $filterStatus ? 'bg-white/10 ring-1 ring-white/30' : '' }}">
                    {{ $label }} <span class="rounded-full bg-white/10 px-2 py-0.5 tabular-nums">{{ $filterStatus ? (int) ($entryStatusCounts[$filterStatus] ?? 0) : $entryStatusCounts->sum() }}</span>
                </a>
            @endforeach
        </nav>
        @include('finance.entries')
    </section>

    @if ($isAdmin)
        @include('finance.budgets')
        <section id="settlement" class="flex flex-col gap-4 rounded-2xl border border-white/10 p-5">
            <h2 class="text-xl font-semibold">Settlement & saldo TL</h2>
            <p class="text-sm text-slate-400">Saldo TL = uang muka + reimbursement − pengeluaran disetujui − pengembalian. Saldo positif dikembalikan TL; saldo negatif diganti perusahaan. Settlement hanya mencatat konfirmasi, tidak melakukan transfer bank.</p>
            <x-money-card label="Arus kas bersih perusahaan untuk trip ini" :amount="$summary['cash']" hint="Biaya yang dibayar TL tidak dipotong lagi dari kas perusahaan." />
            @forelse ($leaders as $leader)
                @php($balance = $summary['balances'][$leader->id] ?? 0)
                <div class="flex flex-wrap justify-between gap-3 border-b border-white/10 py-3"><span>{{ $leader->name }}</span><span class="font-semibold tabular-nums">{{ $balance < 0 ? 'Reimbursement: ' : ($balance > 0 ? 'Perlu dikembalikan: ' : 'Saldo selesai: ') }}Rp {{ number_format(abs($balance), 0, ',', '.') }}</span></div>
            @empty
                <p class="text-sm text-slate-400">Belum ada saldo atau assignment TL.</p>
            @endforelse
            <p class="text-sm text-slate-400">Syarat penutupan: kontrak terisi, tagihan lunas, tidak ada pengajuan pending, dan seluruh saldo TL nol.</p>
            <form method="POST" action="{{ route($trip->finances_locked_at ? 'super-admin.trips.settlement.destroy' : 'super-admin.trips.settlement.store', $trip) }}" class="flex flex-col gap-4 print:hidden" data-confirm="{{ $trip->finances_locked_at ? 'Buka kembali settlement? Data akan dapat diubah kembali.' : 'Tutup settlement? Pastikan semua bukti dan transaksi telah diperiksa.' }}">
                @csrf @if ($trip->finances_locked_at) @method('DELETE') @endif
                <label class="text-sm" for="settlement-reason">Catatan / alasan {{ $trip->finances_locked_at ? 'pembukaan kembali' : 'penutupan' }}</label>
                <input class="rounded-xl border border-white/20 bg-slate-900 px-4 py-3" id="settlement-reason" name="reason" required maxlength="1000">
                <button class="self-start rounded-xl border border-amber-300/50 px-5 py-3 font-semibold text-amber-200">{{ $trip->finances_locked_at ? 'Buka kembali settlement' : 'Selesaikan & kunci keuangan' }}</button>
            </form>
        </section>
        <section id="history" class="rounded-2xl border border-white/10 p-5">
            <h2 class="text-xl font-semibold">Histori keuangan</h2>
            <p class="mt-2 text-xs text-slate-400">30 aktivitas terbaru. Seluruh histori tetap disimpan.</p>
            <div class="mt-4 flex flex-col gap-4">
                @forelse ($audits as $audit)
                    <details class="border-b border-white/10 pb-3">
                        <summary class="cursor-pointer text-sm"><span class="font-medium">{{ $audit->label() }}</span> · {{ $audit->user->name }} · {{ $audit->created_at->format('d/m/Y H:i') }}</summary>
                        @if ($audit->reason)<p class="mt-2 break-words text-sm text-slate-300">Catatan: {{ $audit->reason }}</p>@endif
                        @if ($audit->detailsSummary())
                            <ul class="mt-2 flex flex-wrap gap-2 text-xs text-slate-400">
                                @foreach ($audit->detailsSummary() as $detail)<li class="rounded-full bg-white/5 px-3 py-1">{{ $detail }}</li>@endforeach
                            </ul>
                        @endif
                    </details>
                @empty
                    <p class="text-sm text-slate-400">Belum ada aktivitas keuangan.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
