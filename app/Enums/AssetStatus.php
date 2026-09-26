<?php

namespace App\Enums;

enum AssetStatus: string
{
    use HasLabel;

    case Available = 'AVAILABLE';
    case Assigned = 'ASSIGNED';
    case OnLoan = 'ON_LOAN';
    case InRepair = 'IN_REPAIR';
    case PendingDisposal = 'PENDING_DISPOSAL';
    case Disposed = 'DISPOSED';
    case Lost = 'LOST';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Assigned => 'Ditugaskan',
            self::OnLoan => 'Dipinjam',
            self::InRepair => 'Dalam Perbaikan',
            self::PendingDisposal => 'Menunggu Disposal',
            self::Disposed => 'Dihapus',
            self::Lost => 'Hilang',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'available',
            self::Assigned => 'assigned',
            self::OnLoan => 'loan',
            self::InRepair => 'repair',
            self::PendingDisposal => 'disposal',
            self::Disposed => 'neutral',
            self::Lost => 'disposal',
        };
    }
}
