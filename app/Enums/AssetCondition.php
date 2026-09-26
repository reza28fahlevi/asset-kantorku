<?php

namespace App\Enums;

enum AssetCondition: string
{
    use HasLabel;

    case Good = 'GOOD';
    case Fair = 'FAIR';
    case Poor = 'POOR';
    case Damaged = 'DAMAGED';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Fair => 'Cukup',
            self::Poor => 'Kurang',
            self::Damaged => 'Rusak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'available',
            self::Fair => 'assigned',
            self::Poor => 'repair',
            self::Damaged => 'disposal',
        };
    }
}
