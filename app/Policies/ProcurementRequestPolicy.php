<?php

namespace App\Policies;

use App\Enums\ProcurementStatus;
use App\Models\ProcurementRequest;
use App\Models\User;

class ProcurementRequestPolicy extends RequestPolicy
{
    protected function createPermission(): string
    {
        return 'procurement.create';
    }

    protected function cancellableStatuses(): array
    {
        return [ProcurementStatus::Draft, ProcurementStatus::PendingApproval];
    }

    protected function draftStatus(): \BackedEnum
    {
        return ProcurementStatus::Draft;
    }

    /** Draft hanya dapat diubah oleh pemiliknya. */
    public function update(User $user, ProcurementRequest $request): bool
    {
        return $this->submit($user, $request);
    }

    public function order(User $user, ProcurementRequest $request): bool
    {
        return $request->status === ProcurementStatus::Approved && $user->hasPermission('procurement.order');
    }

    public function receive(User $user, ProcurementRequest $request): bool
    {
        return in_array($request->status, [ProcurementStatus::Ordered, ProcurementStatus::PartiallyReceived], true)
            && $user->hasPermission('procurement.receive');
    }

    public function close(User $user, ProcurementRequest $request): bool
    {
        return $request->status === ProcurementStatus::PartiallyReceived && $user->hasPermission('procurement.receive');
    }
}
