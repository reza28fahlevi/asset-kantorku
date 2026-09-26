<?php

namespace Tests\Unit\Policies;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\ProcurementStatus;
use App\Models\ApprovalStep;
use App\Policies\ApprovalStepPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class ApprovalStepPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private function pendingStep(int $requester = 5, int $approver = 4): ApprovalStep
    {
        $request = $this->makeProcurement($requester, ProcurementStatus::PendingApproval);

        return $this->attachApproval($request, ApprovalType::Procurement, $approver);
    }

    public function test_policy_terdaftar_untuk_approval_step(): void
    {
        $this->assertInstanceOf(ApprovalStepPolicy::class, Gate::getPolicyFor(ApprovalStep::class));
    }

    public function test_approver_yang_ditunjuk_boleh_memutuskan_step_pending(): void
    {
        $step = $this->pendingStep();
        $this->assertTrue(Gate::forUser($this->user('manager.it'))->allows('decide', $step));
    }

    public function test_manager_lain_admin_dan_requester_tidak_boleh_memutuskan(): void
    {
        $step = $this->pendingStep();
        foreach (['direktur', 'admin.aset', 'staff', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('decide', $step), $name);
        }
    }

    public function test_step_yang_sudah_diputuskan_tidak_bisa_diputuskan_ulang(): void
    {
        foreach ([ApprovalStatus::Approved, ApprovalStatus::Rejected, ApprovalStatus::Cancelled] as $status) {
            $request = $this->makeProcurement(5, ProcurementStatus::PendingApproval);
            $step = $this->attachApproval($request, ApprovalType::Procurement, 4, $status);
            $this->assertFalse(Gate::forUser($this->user('manager.it'))->allows('decide', $step), $status->value);
        }
    }

    public function test_approver_tanpa_permission_approval_decide_ditolak(): void
    {
        // Step ditujukan ke staff (emp 5) yang tidak memiliki approval.decide (mis. role sudah dicabut).
        $step = $this->pendingStep(requester: 7, approver: 5);
        $this->assertFalse(Gate::forUser($this->user('staff'))->allows('decide', $step));
    }

    public function test_approver_yang_akunnya_nonaktif_ditolak(): void
    {
        $step = $this->pendingStep();
        $manager = $this->user('manager.it');
        $manager->is_active = false; // tidak disimpan: cukup untuk menguji policy

        $this->assertFalse(Gate::forUser($manager)->allows('decide', $step));
    }

    public function test_approver_yang_sama_dengan_requester_ditolak(): void
    {
        // Trigger DB menolak step semacam ini; policy tetap menjadi lapis pertahanan kedua.
        $step = $this->pendingStep();
        $step->approvalRequest->requester_employee_id = 4;

        $this->assertFalse((new ApprovalStepPolicy)->decide($this->user('manager.it'), $step));
    }

    public function test_user_tanpa_karyawan_tidak_bisa_menjadi_approver(): void
    {
        $step = $this->pendingStep();
        $orphan = $this->makeUser(null, ['manager']);

        $this->assertFalse($orphan->isApprover());
        $this->assertFalse(Gate::forUser($orphan)->allows('decide', $step));
    }
}
