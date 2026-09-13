<?php

namespace App;

enum TripStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Ongoing = 'ongoing';
    case AwaitingSettlement = 'awaiting_settlement';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::Ongoing => 'Ongoing',
            self::AwaitingSettlement => 'Awaiting Settlement',
            self::Closed => 'Closed',
        };
    }
}
