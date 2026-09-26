<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    use HasLabel;

    case Draft = 'DRAFT';
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Pending => 'pending',
            self::Approved => 'available',
            self::Rejected => 'disposal',
            self::Cancelled => 'neutral',
        };
    }
}
