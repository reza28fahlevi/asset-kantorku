<?php

namespace App\Enums;

enum ApprovalType: string
{
    use HasLabel;

    case Procurement = 'PROCUREMENT';
    case Assignment = 'ASSIGNMENT';
    case Loan = 'LOAN';
    case LoanExtension = 'LOAN_EXTENSION';
    case Disposal = 'DISPOSAL';

    public function label(): string
    {
        return match ($this) {
            self::Procurement => 'Procurement',
            self::Assignment => 'Assignment',
            self::Loan => 'Peminjaman',
            self::LoanExtension => 'Perpanjangan Pinjaman',
            self::Disposal => 'Disposal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Procurement => 'pending',
            self::Assignment => 'assigned',
            self::Loan => 'loan',
            self::LoanExtension => 'loan',
            self::Disposal => 'disposal',
        };
    }
}
