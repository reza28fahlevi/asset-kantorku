<?php

namespace App\Policies;

use App\Models\AssetLoan;
use App\Models\User;

class AssetLoanPolicy
{
    public function return(User $user, AssetLoan $loan): bool
    {
        return $loan->isActive() && $user->hasPermission('loan.return');
    }

    /** Perpanjangan: peminjam sendiri, atau admin yang boleh mengajukan atas nama karyawan. */
    public function extend(User $user, AssetLoan $loan): bool
    {
        return $loan->isActive()
            && $user->hasPermission('loan.extend')
            && ($loan->borrower_employee_id === $user->employee_id || $user->hasPermission('loan.create_for_others'));
    }
}
