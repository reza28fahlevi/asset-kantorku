<?php

namespace App\Policies;

use App\Enums\DisposalStatus;
use App\Models\DisposalRequest;
use App\Models\User;

class DisposalRequestPolicy extends RequestPolicy
{
    protected function createPermission(): string
    {
        return 'disposal.create';
    }

    protected function cancellableStatuses(): array
    {
        return [DisposalStatus::Draft, DisposalStatus::PendingApproval];
    }

    protected function draftStatus(): \BackedEnum
    {
        return DisposalStatus::Draft;
    }

    public function execute(User $user, DisposalRequest $request): bool
    {
        return $request->status === DisposalStatus::Approved && $user->hasPermission('disposal.execute');
    }
}
