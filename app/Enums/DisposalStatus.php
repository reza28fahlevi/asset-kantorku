<?php

namespace App\Enums;

enum DisposalStatus: string
{
    use HasLabel;

    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Rejected = 'REJECTED';
    case Approved = 'APPROVED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Menunggu Approval',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingApproval => 'pending',
            self::Rejected => 'disposal',
            self::Approved => 'available',
            self::Completed => 'neutral',
            self::Cancelled => 'neutral',
        };
    }
}
