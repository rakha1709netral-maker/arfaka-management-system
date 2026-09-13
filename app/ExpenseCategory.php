<?php

namespace App;

enum ExpenseCategory: string
{
    case Transport = 'transport';
    case Accommodation = 'accommodation';
    case Meals = 'meals';
    case Tickets = 'tickets';
    case Equipment = 'equipment';
    case Fee = 'fee';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transport => 'Transportasi',
            self::Accommodation => 'Akomodasi',
            self::Meals => 'Konsumsi',
            self::Tickets => 'Tiket & wisata',
            self::Equipment => 'Perlengkapan',
            self::Fee => 'Honor, tip & komisi',
            self::Other => 'Lainnya',
        };
    }
}
