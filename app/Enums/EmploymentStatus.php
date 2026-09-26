<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    use HasLabel;

    case Active = 'ACTIVE';
    case OnLeave = 'ON_LEAVE';
    case Inactive = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::OnLeave => 'Cuti',
            self::Inactive => 'Nonaktif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'available',
            self::OnLeave => 'repair',
            self::Inactive => 'neutral',
        };
    }
}
