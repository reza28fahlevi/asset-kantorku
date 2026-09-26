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

    /** @return \BackedEnum status disetujui namun belum ditindaklanjuti (masih boleh dibatalkan dengan alasan) */
    abstract protected function approvedStatus(): \BackedEnum;

    /** Permission petugas yang menindaklanjuti permintaan disetujui (mis. "assignment.handover"). */
    abstract protected function fulfillPermission(): string;

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

    /**
     * Draft/menunggu approval: hanya requester. Sudah disetujui tapi belum ditindaklanjuti:
     * requester atau petugas penindak lanjut (alasan wajib, dicek di service).
     */
    public function cancel(User $user, Model $request): bool
    {
        if ($request->status === $this->approvedStatus()) {
            return $request->isOwnedBy($user) || $user->hasPermission($this->fulfillPermission());
        }

        return in_array($request->status, $this->cancellableStatuses(), true)
            && $request->isOwnedBy($user);
    }
}
