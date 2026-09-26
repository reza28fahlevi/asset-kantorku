<?php

namespace Tests\Unit\Policies;

use App\Enums\ExtensionStatus;
use App\Models\AssetLoan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class LoanExtensionRequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private AssetLoan $loan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loan = $this->makeLoan(5);
    }

    public function test_pemohon_boleh_membatalkan_perpanjangan_yang_menunggu(): void
    {
        $extension = $this->makeExtension($this->loan, 5, ExtensionStatus::PendingApproval, $this->user('staff')->id);
        $this->assertTrue(Gate::forUser($this->user('staff'))->allows('cancel', $extension));
    }

    public function test_pembuat_atas_nama_peminjam_boleh_membatalkan(): void
    {
        $admin = $this->user('admin.aset');
        $extension = $this->makeExtension($this->loan, 3, ExtensionStatus::PendingApproval, $admin->id);
        $this->assertTrue(Gate::forUser($admin)->allows('cancel', $extension));
    }

    public function test_bukan_pemohon_tidak_boleh_membatalkan(): void
    {
        $extension = $this->makeExtension($this->loan, 5, ExtensionStatus::PendingApproval, $this->user('staff')->id);
        foreach (['admin.aset', 'manager.it', 'auditor', 'direktur'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('cancel', $extension), $name);
        }
    }

    public function test_perpanjangan_yang_sudah_diputuskan_tidak_bisa_dibatalkan(): void
    {
        foreach ([ExtensionStatus::Approved, ExtensionStatus::Rejected, ExtensionStatus::Cancelled] as $status) {
            $loan = $this->makeLoan(5);
            $extension = $this->makeExtension($loan, 5, $status, $this->user('staff')->id);
            $this->assertFalse(Gate::forUser($this->user('staff'))->allows('cancel', $extension), $status->value);
        }
    }
}
