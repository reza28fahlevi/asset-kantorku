<?php

namespace Tests\Unit\Models;

use App\Enums\EmploymentStatus;
use App\Enums\LoanStatus;
use App\Models\Employee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class EmployeeTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_is_active_dan_scope_active(): void
    {
        $active = $this->makeEmployee();
        $leave = $this->makeEmployee(['employment_status' => EmploymentStatus::OnLeave]);
        $inactive = $this->makeEmployee(['employment_status' => EmploymentStatus::Inactive]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($leave->isActive());
        $this->assertFalse($inactive->isActive());
        $this->assertSame([$active->id], Employee::whereIn('id', [$active->id, $leave->id, $inactive->id])->active()->pluck('id')->all());
    }

    public function test_display_name_menyertakan_nomor_karyawan(): void
    {
        $employee = $this->makeEmployee(['name' => 'Budi', 'employee_no' => 'EMP-UT3-1']);
        $this->assertSame('Budi (EMP-UT3-1)', $employee->display_name);
    }

    public function test_rantai_atasan(): void
    {
        $staff = Employee::find(5); // atasan 4, atasan 4 = 1

        $this->assertTrue($staff->hasInManagerChain(4));
        $this->assertTrue($staff->hasInManagerChain(1));
        $this->assertFalse($staff->hasInManagerChain(3));
        $this->assertFalse($staff->hasInManagerChain(5), 'diri sendiri bukan atasan');
        $this->assertFalse(Employee::find(1)->hasInManagerChain(4), 'direktur tanpa atasan');
    }

    public function test_rantai_atasan_berhenti_pada_siklus(): void
    {
        $a = $this->makeEmployee();
        $b = $this->makeEmployee(['manager_employee_id' => $a->id]);
        $a->update(['manager_employee_id' => $b->id]); // siklus a <-> b

        $this->assertTrue($a->fresh()->hasInManagerChain($b->id));
        $this->assertFalse($a->fresh()->hasInManagerChain(999999), 'tidak berputar tanpa henti');
    }

    public function test_aset_aktif_yang_dipegang(): void
    {
        $employee = $this->makeEmployee();
        $this->makeAssignment($employee->id);
        $this->makeAssignment($employee->id, returned: true);
        $this->makeLoan($employee->id);
        $this->makeLoan($employee->id, LoanStatus::Returned);

        $this->assertSame(1, $employee->activeAssignments()->count());
        $this->assertSame(1, $employee->activeLoans()->count());
    }
}
