<?php

namespace App\Services\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\User;
use BackedEnum;

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
}
