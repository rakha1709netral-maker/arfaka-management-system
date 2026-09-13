<section id="budget" class="flex flex-col gap-4">
    <h2 class="text-xl font-semibold">Rencana anggaran biaya</h2>
    <p class="text-sm text-slate-400">RAB adalah rencana, bukan pengeluaran. Total dihitung dari jumlah × harga satuan. Honor, tip, dan komisi dapat dicatat sebagai kategori biaya dengan nominal manual.</p>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($categories as $category)
            @php($planned = $budgets->filter(fn ($item) => $item->category === $category)->sum(fn ($item) => $item->total()))
            @php($actual = $summary['categories'][$category->value] ?? 0)
            @if ($planned || $actual)
                <div class="rounded-xl border border-white/10 p-4"><p class="font-semibold">{{ $category->label() }}</p><p class="mt-2 text-sm text-slate-400">RAB: Rp {{ number_format($planned, 0, ',', '.') }}</p><p class="text-sm">Realisasi: Rp {{ number_format($actual, 0, ',', '.') }}</p><p class="mt-1 text-xs {{ $actual > $planned ? 'text-amber-300' : 'text-emerald-300' }}">{{ $actual > $planned ? 'Melebihi RAB' : 'Sisa RAB' }}: Rp {{ number_format(abs($planned - $actual), 0, ',', '.') }}</p></div>
            @endif
        @endforeach
    </div>
    @if (! $trip->finances_locked_at)
        <details class="rounded-2xl border border-white/10 bg-slate-900 p-5 print:hidden">
            <summary class="cursor-pointer font-semibold text-emerald-300">+ Tambah item RAB</summary>
            <form method="POST" action="{{ route('super-admin.trips.budgets.store', $trip) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <label class="flex flex-col gap-2 text-sm">Kategori<select name="category" class="rounded-xl border border-white/20 bg-slate-900 p-3">@foreach ($categories as $category)<option value="{{ $category->value }}">{{ $category->label() }}</option>@endforeach</select></label>
                <label class="flex flex-col gap-2 text-sm">Keterangan<input name="description" required maxlength="255" class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                <label class="flex flex-col gap-2 text-sm">Jumlah<input name="quantity" type="number" min="1" max="100000" step="1" required class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                <label class="flex flex-col gap-2 text-sm">Harga satuan (Rp)<input name="unit_amount" type="number" min="1" max="10000000" step="1" required class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                <button class="justify-self-start rounded-xl bg-white px-5 py-3 font-semibold text-slate-950">Simpan RAB</button>
            </form>
        </details>
    @endif
    @forelse ($budgets as $item)
        <article class="rounded-xl border border-white/10 p-4">
            <div class="flex flex-wrap justify-between gap-3"><div><h3 class="break-words font-semibold">{{ $item->description }}</h3><p class="mt-1 text-xs text-slate-400">{{ $item->category->label() }} · {{ $item->quantity }} × Rp {{ number_format($item->unit_amount, 0, ',', '.') }}</p></div><p class="tabular-nums">Rp {{ number_format($item->total(), 0, ',', '.') }}</p></div>
            @if (! $trip->finances_locked_at)
                <details class="mt-3 print:hidden">
                    <summary class="cursor-pointer text-sm text-slate-400">Edit / arsipkan item</summary>
                    <form method="POST" action="{{ route('super-admin.trips.budgets.update', [$trip, $item]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="flex flex-col gap-1 text-sm">Kategori<select name="category" class="rounded-xl border border-white/20 bg-slate-900 p-3">@foreach ($categories as $category)<option value="{{ $category->value }}" @selected($category === $item->category)>{{ $category->label() }}</option>@endforeach</select></label>
                        <label class="flex flex-col gap-1 text-sm">Keterangan<input name="description" value="{{ $item->description }}" required maxlength="255" class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                        <label class="flex flex-col gap-1 text-sm">Jumlah<input name="quantity" value="{{ $item->quantity }}" type="number" min="1" max="100000" step="1" required class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                        <label class="flex flex-col gap-1 text-sm">Harga satuan (Rp)<input name="unit_amount" value="{{ $item->unit_amount }}" type="number" min="1" max="10000000" step="1" required class="rounded-xl border border-white/20 bg-slate-900 p-3"></label>
                        <button class="justify-self-start rounded-xl border border-white/20 px-4 py-3 text-sm">Simpan perubahan</button>
                    </form>
                    <form method="POST" action="{{ route('super-admin.trips.budgets.destroy', [$trip, $item]) }}" class="mt-4 flex flex-col gap-3 sm:flex-row" data-confirm="Arsipkan item ini dari RAB?">
                        @csrf @method('DELETE')
                        <input name="reason" aria-label="Alasan arsip item RAB" placeholder="Alasan arsip (wajib)" required maxlength="1000" class="min-w-0 flex-1 rounded-xl border border-white/20 bg-slate-900 p-3">
                        <button class="rounded-xl border border-red-400/30 px-4 py-3 text-sm text-red-200">Arsipkan</button>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <p class="text-sm text-slate-400">Belum ada item RAB.</p>
    @endforelse
</section>
