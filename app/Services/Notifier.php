<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;

/**
 * Mengirim notifikasi in-app SETELAH transaksi commit agar tidak ada notifikasi
 * untuk perubahan yang di-rollback.
 */
class Notifier
{
    public function toEmployee(?Employee $employee, string $title, string $message, ?string $url = null, string $icon = 'bi-bell'): void
    {
        $user = $employee?->user;
        if ($user && $user->is_active) {
            $this->toUser($user, $title, $message, $url, $icon);
        }
    }

    public function toUser(User $user, string $title, string $message, ?string $url = null, string $icon = 'bi-bell'): void
    {
        DB::afterCommit(fn () => $user->notify(new AppNotification($title, $message, $url, $icon)));
    }

    /** Kirim ke semua user aktif yang memiliki permission tertentu. */
    public function toPermission(string $permission, string $title, string $message, ?string $url = null, string $icon = 'bi-bell'): void
    {
        User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
            ->get()
            ->each(fn (User $user) => $this->toUser($user, $title, $message, $url, $icon));
    }
}
