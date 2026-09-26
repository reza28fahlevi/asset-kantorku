<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\AssetLoanRequest;
use App\Models\User;

class AssetLoanRequestPolicy extends RequestPolicy
{
    protected function createPermission(): string
    {
        return 'loan.create';
    }

    protected function cancellableStatuses(): array
    {
        return [RequestStatus::Draft, RequestStatus::PendingApproval];
    }

    protected function draftStatus(): \BackedEnum
    {
        return RequestStatus::Draft;
    }

    protected function approvedStatus(): \BackedEnum
    {
        return RequestStatus::Approved;
    }

    protected function fulfillPermission(): string
    {
        return 'loan.checkout';
    }

    public function checkout(User $user, AssetLoanRequest $request): bool
    {
        return $request->status === RequestStatus::Approved && $user->hasPermission('loan.checkout');
    }
}
