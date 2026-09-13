<?php

namespace App;

enum TripAssignmentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
        };
    }
}
