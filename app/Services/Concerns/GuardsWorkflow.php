<?php

namespace App\Services\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

trait GuardsWorkflow
{
    /** Karyawan yang tertaut pada akun user; wajib ada & aktif untuk membuat permintaan. */
    protected function actingEmployee(User $user): Employee
    {
        $employee = $user->employee;
        if (! $employee) {
            throw new BusinessRuleException('Akun Anda belum ditautkan ke master karyawan. Hubungi System Administrator.');
        }
        if (! $employee->isActive()) {
            throw new BusinessRuleException('Karyawan nonaktif/cuti tidak dapat membuat permintaan baru.');
        }

        return $employee;
    }

    /** @param  array<BackedEnum>  $allowed */
    protected function ensureStatus(BackedEnum $current, array $allowed, string $action): void
    {
        if (! in_array($current, $allowed, true)) {
            $label = method_exists($current, 'label') ? $current->label() : $current->value;
            throw new BusinessRuleException("Tidak dapat {$action}: status saat ini {$label}.");
        }
    }

    protected function ensureActiveEmployee(Employee $employee, string $role): void
    {
        if (! $employee->isActive()) {
            throw new BusinessRuleException("{$role} {$employee->name} tidak aktif sehingga tidak dapat dipilih.");
        }
    }

    /** Alasan wajib (min. 5 karakter) bila permintaan yang sudah disetujui dibatalkan. */
    protected function cancelReason(bool $wasApproved, ?string $reason): ?string
    {
        $reason = trim((string) $reason);
        if ($wasApproved && mb_strlen($reason) < 5) {
            throw new BusinessRuleException('Alasan pembatalan wajib diisi (min. 5 karakter) untuk permintaan yang sudah disetujui.');
        }

        return $reason !== '' ? $reason : null;
    }

    /** Audit + notifikasi requester untuk pembatalan permintaan yang sudah disetujui. */
    protected function afterCancel(Model $request, bool $wasApproved, ?User $user, ?string $reason, string $url): void
    {
        if (! $wasApproved) {
            return;
        }

        AuditLogger::log('cancelled_after_approval', $request, ['status' => 'APPROVED'], ['status' => 'CANCELLED', 'reason' => $reason]);

        if ($user?->employee_id !== $request->requester_employee_id) {
            app(Notifier::class)->toEmployee(
                $request->requester,
                'Permintaan dibatalkan',
                "{$request->request_no} yang sudah disetujui dibatalkan oleh ".($user?->name ?? 'sistem').": {$reason}",
                $url,
                'bi-x-circle',
            );
        }
    }
}
