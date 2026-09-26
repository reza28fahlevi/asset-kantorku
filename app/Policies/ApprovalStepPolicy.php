<?php

namespace App\Policies;

use App\Models\ApprovalStep;
use App\Models\User;

class ApprovalStepPolicy
{
    /**
     * Hanya approver yang dituju (snapshot), bukan requester, pada step yang masih PENDING.
     */
    public function decide(User $user, ApprovalStep $step): bool
    {
        return $step->isPending()
            && $user->isApprover()
            && $user->employee_id === $step->approver_employee_id
            && $step->approvalRequest->requester_employee_id !== $user->employee_id;
    }
}
