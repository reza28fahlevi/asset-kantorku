<?php

namespace App\Enums;

enum LoanStatus: string
{
    use HasLabel;

    case CheckedOut = 'CHECKED_OUT';
    case Overdue = 'OVERDUE';
    case Returned = 'RETURNED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::CheckedOut => 'Dipinjam',
            self::Overdue => 'Terlambat',
            self::Returned => 'Dikembalikan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CheckedOut => 'loan',
            self::Overdue => 'disposal',
            self::Returned => 'available',
            self::Cancelled => 'neutral',
        };
    }
}
