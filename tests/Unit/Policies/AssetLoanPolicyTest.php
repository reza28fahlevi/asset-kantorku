<?php

namespace Tests\Unit\Policies;

use App\Enums\LoanStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetLoanPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_pengembalian_loan_aktif_dan_terlambat_oleh_admin(): void
    {
        $admin = $this->user('admin.aset');
        $this->assertTrue(Gate::forUser($admin)->allows('return', $this->makeLoan(5, LoanStatus::CheckedOut)));
        $this->assertTrue(Gate::forUser($admin)->allows('return', $this->makeLoan(5, LoanStatus::Overdue, dueAt: now()->subDay()->startOfMinute())));
    }

    public function test_pengembalian_ditolak_untuk_loan_selesai_dan_role_tanpa_permission(): void
    {
        $admin = $this->user('admin.aset');
        $this->assertFalse(Gate::forUser($admin)->allows('return', $this->makeLoan(5, LoanStatus::Returned)));
        $this->assertFalse(Gate::forUser($admin)->allows('return', $this->makeLoan(5, LoanStatus::Cancelled)));

        $active = $this->makeLoan(5);
        foreach (['staff', 'manager.it', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('return', $active), $name);
        }
    }

    public function test_perpanjangan_oleh_peminjam_sendiri(): void
    {
        $staff = $this->user('staff');
        $this->assertTrue(Gate::forUser($staff)->allows('extend', $this->makeLoan(5)));
        $this->assertTrue(Gate::forUser($staff)->allows('extend', $this->makeLoan(5, LoanStatus::Overdue, dueAt: now()->subDay()->startOfMinute())));
    }

    public function test_perpanjangan_oleh_admin_atas_nama_peminjam(): void
    {
        // Dimas (emp 7) tidak punya akun; admin boleh mengajukan atas namanya.
        $this->assertTrue(Gate::forUser($this->user('admin.aset'))->allows('extend', $this->makeLoan(7)));
    }

    public function test_perpanjangan_ditolak_untuk_bukan_peminjam_tanpa_create_for_others(): void
    {
        $loan = $this->makeLoan(5);
        foreach (['manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('extend', $loan), $name);
        }
    }

    public function test_perpanjangan_ditolak_untuk_loan_tidak_aktif(): void
    {
        foreach ([LoanStatus::Returned, LoanStatus::Cancelled] as $status) {
            $loan = $this->makeLoan(5, $status);
            $this->assertFalse(Gate::forUser($this->user('staff'))->allows('extend', $loan), $status->value);
            $this->assertFalse(Gate::forUser($this->user('admin.aset'))->allows('extend', $loan), $status->value);
        }
    }

    public function test_peminjam_tanpa_permission_extend_ditolak(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->makeUser($employee->id, ['auditor']);
        $this->assertFalse(Gate::forUser($user)->allows('extend', $this->makeLoan($employee->id)));
    }
}
