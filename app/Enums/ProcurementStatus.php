<?php

namespace App\Enums;

enum ProcurementStatus: string
{
    use HasLabel;

    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Rejected = 'REJECTED';
    case Approved = 'APPROVED';
    case Ordered = 'ORDERED';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Menunggu Approval',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui',
            self::Ordered => 'Dipesan',
            self::PartiallyReceived => 'Diterima Sebagian',
            self::Received => 'Diterima',
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
            self::Ordered => 'assigned',
            self::PartiallyReceived => 'loan',
            self::Received => 'available',
            self::Cancelled => 'neutral',
        };
    }
}
