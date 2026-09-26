<?php

namespace App\Policies;

use App\Enums\ExtensionStatus;
use App\Models\LoanExtensionRequest;
use App\Models\User;

class LoanExtensionRequestPolicy
{
    public function cancel(User $user, LoanExtensionRequest $extension): bool
    {
        return $extension->status === ExtensionStatus::PendingApproval && $extension->isOwnedBy($user);
    }
}
