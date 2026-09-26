<?php

namespace App\Enums;

enum DisposalReason: string
{
    use HasLabel;

    case Damaged = 'DAMAGED';
    case Obsolete = 'OBSOLETE';
    case Uneconomical = 'UNECONOMICAL';
    case Lost = 'LOST';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Rusak',
            self::Obsolete => 'Usang',
            self::Uneconomical => 'Tidak Ekonomis',
            self::Lost => 'Hilang',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Damaged => 'disposal',
            self::Obsolete => 'repair',
            self::Uneconomical => 'pending',
            self::Lost => 'disposal',
            self::Other => 'neutral',
        };
    }
}
