<?php

namespace App\Enums;

enum DisposalMethod: string
{
    use HasLabel;

    case Sale = 'SALE';
    case Donation = 'DONATION';
    case Scrap = 'SCRAP';
    case Recycle = 'RECYCLE';
    case WriteOff = 'WRITE_OFF';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Dijual / Lelang',
            self::Donation => 'Hibah / Donasi',
            self::Scrap => 'Dimusnahkan',
            self::Recycle => 'Didaur Ulang',
            self::WriteOff => 'Penghapusbukuan',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sale => 'neutral',
            self::Donation => 'neutral',
            self::Scrap => 'neutral',
            self::Recycle => 'neutral',
            self::WriteOff => 'neutral',
            self::Other => 'neutral',
        };
    }
}
