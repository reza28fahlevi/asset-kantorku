<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\AssignmentRequest;
use App\Models\User;

class AssignmentRequestPolicy extends RequestPolicy
{
    protected function createPermission(): string
    {
        return 'assignment.create';
    }

    protected function cancellableStatuses(): array
    {
        return [RequestStatus::Draft, RequestStatus::PendingApproval];
    }

    protected function draftStatus(): \BackedEnum
    {
        return RequestStatus::Draft;
    }

    public function handover(User $user, AssignmentRequest $request): bool
    {
        return $request->status === RequestStatus::Approved && $user->hasPermission('assignment.handover');
    }
}
