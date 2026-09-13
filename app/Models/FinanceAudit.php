<?php

namespace App\Models;

use App\FinanceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'reason', 'details'])]
class FinanceAudit extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function label(): string
    {
        return match ($this->action) {
            'entry.created' => 'Transaksi dicatat',
            'entry.approved' => 'Pengeluaran disetujui',
            'entry.rejected' => 'Pengeluaran ditolak',
            'entry.void' => 'Transaksi dibatalkan',
            'contract.updated' => 'Nilai kontrak diperbarui',
            'budget.created' => 'Item RAB ditambahkan',
            'budget.updated' => 'Item RAB diperbarui',
            'budget.archived' => 'Item RAB diarsipkan',
            'settlement.closed' => 'Settlement diselesaikan',
            'settlement.reopened' => 'Settlement dibuka kembali',
            'trip.confirmed_by_payment' => 'Trip dikonfirmasi oleh pembayaran pertama',
            default => 'Aktivitas keuangan',
        };
    }

    /** @return array<int, string> */
    public function detailsSummary(): array
    {
        $summary = [];

        if (isset($this->details['entry_id'])) {
            $summary[] = 'Transaksi #'.$this->details['entry_id'];
        }
        if (isset($this->details['type'])) {
            $type = FinanceType::tryFrom((string) $this->details['type']);
            $summary[] = 'Jenis: '.($type?->label() ?? $this->details['type']);
        }
        if (isset($this->details['amount'])) {
            $summary[] = 'Nominal: Rp '.number_format((int) $this->details['amount'], 0, ',', '.');
        }
        if (array_key_exists('previous', $this->details)) {
            $summary[] = 'Nilai sebelumnya: '.($this->details['previous'] === null
                ? 'belum diisi'
                : 'Rp '.number_format((int) $this->details['previous'], 0, ',', '.'));
        }
        if (isset($this->details['previous_status'])) {
            $summary[] = 'Status sebelumnya: '.$this->statusLabel((string) $this->details['previous_status']);
        }

        return $summary;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu pemeriksaan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'void' => 'Dibatalkan',
            default => $status,
        };
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
