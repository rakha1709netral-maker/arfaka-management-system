<?php

namespace App;

enum FinanceType: string
{
    case Payment = 'payment';
    case Expense = 'expense';
    case Advance = 'advance';
    case Return = 'return';
    case Reimbursement = 'reimbursement';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Pembayaran customer',
            self::Expense => 'Pengeluaran',
            self::Advance => 'Uang muka operasional TL',
            self::Return => 'Pengembalian saldo TL',
            self::Reimbursement => 'Pembayaran reimbursement',
        };
    }
}
