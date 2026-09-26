<?php

namespace App\Enums;

enum ExtensionStatus: string
{
    use HasLabel;

    case PendingApproval = 'PENDING_APPROVAL';
    case Rejected = 'REJECTED';
    case Approved = 'APPROVED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Menunggu Approval',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingApproval => 'pending',
            self::Rejected => 'disposal',
            self::Approved => 'available',
            self::Cancelled => 'neutral',
        };
    }
}
