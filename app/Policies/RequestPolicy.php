<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan bersama untuk permintaan ber-approval. Server tetap menjadi otoritas:
 * policy menentukan tombol di UI sekaligus dicek ulang di controller & service.
 */
abstract class RequestPolicy
{
    /** Permission untuk membuat permintaan, mis. "procurement.create". */
    abstract protected function createPermission(): string;

    /** @return array<\BackedEnum> status yang masih boleh dibatalkan requester */
    abstract protected function cancellableStatuses(): array;

    /** @return \BackedEnum status draft */
    abstract protected function draftStatus(): \BackedEnum;

    public function view(User $user, Model $request): bool
    {
        return $request->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->createPermission());
    }

    public function submit(User $user, Model $request): bool
    {
        return $request->status === $this->draftStatus()
            && $request->isOwnedBy($user)
            && $user->hasPermission($this->createPermission());
    }

    public function cancel(User $user, Model $request): bool
    {
        return in_array($request->status, $this->cancellableStatuses(), true)
            && $request->isOwnedBy($user);
    }
}
