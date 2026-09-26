<?php

namespace App\Enums;

enum AssetEventType: string
{
    use HasLabel;

    case Registered = 'REGISTERED';
    case Received = 'RECEIVED';
    case Updated = 'UPDATED';
    case StatusChanged = 'STATUS_CHANGED';
    case LocationChanged = 'LOCATION_CHANGED';
    case Assigned = 'ASSIGNED';
    case AssignmentReturned = 'ASSIGNMENT_RETURNED';
    case LoanedOut = 'LOANED_OUT';
    case LoanReturned = 'LOAN_RETURNED';
    case LoanExtended = 'LOAN_EXTENDED';
    case LoanOverdue = 'LOAN_OVERDUE';
    case RepairStarted = 'REPAIR_STARTED';
    case RepairFinished = 'REPAIR_FINISHED';
    case MarkedLost = 'MARKED_LOST';
    case DisposalRequested = 'DISPOSAL_REQUESTED';
    case DisposalRejected = 'DISPOSAL_REJECTED';
    case DisposalCancelled = 'DISPOSAL_CANCELLED';
    case Disposed = 'DISPOSED';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrasi aset',
            self::Received => 'Diterima dari procurement',
            self::Updated => 'Data aset diperbarui',
            self::StatusChanged => 'Status berubah',
            self::LocationChanged => 'Lokasi berubah',
            self::Assigned => 'Serah-terima assignment',
            self::AssignmentReturned => 'Pengembalian assignment',
            self::LoanedOut => 'Serah-terima peminjaman',
            self::LoanReturned => 'Pengembalian peminjaman',
            self::LoanExtended => 'Peminjaman diperpanjang',
            self::LoanOverdue => 'Peminjaman terlambat',
            self::RepairStarted => 'Masuk perbaikan',
            self::RepairFinished => 'Selesai perbaikan',
            self::MarkedLost => 'Dilaporkan hilang',
            self::DisposalRequested => 'Pengajuan disposal',
            self::DisposalRejected => 'Disposal ditolak',
            self::DisposalCancelled => 'Disposal dibatalkan',
            self::Disposed => 'Aset dihapus (disposed)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered, self::Received, self::RepairFinished, self::AssignmentReturned, self::LoanReturned => 'available',
            self::Assigned => 'assigned',
            self::LoanedOut, self::LoanExtended => 'loan',
            self::RepairStarted => 'repair',
            self::LoanOverdue, self::MarkedLost, self::DisposalRequested, self::Disposed => 'disposal',
            default => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Registered, self::Received => 'bi-box-seam',
            self::Updated => 'bi-pencil-square',
            self::StatusChanged => 'bi-arrow-repeat',
            self::LocationChanged => 'bi-geo-alt',
            self::Assigned => 'bi-person-check',
            self::AssignmentReturned, self::LoanReturned => 'bi-arrow-return-left',
            self::LoanedOut => 'bi-box-arrow-up-right',
            self::LoanExtended => 'bi-calendar-plus',
            self::LoanOverdue => 'bi-alarm',
            self::RepairStarted, self::RepairFinished => 'bi-tools',
            self::MarkedLost => 'bi-question-octagon',
            self::DisposalRequested, self::DisposalRejected, self::DisposalCancelled => 'bi-trash3',
            self::Disposed => 'bi-archive',
        };
    }
}
