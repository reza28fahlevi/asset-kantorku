<?php

namespace Tests\Unit\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalType;
use App\Enums\DisposalStatus;
use App\Enums\ExtensionStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\LoanExtensionRequest;
use App\Models\ProcurementRequest;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class ApprovalRequestTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private const SUBJECTS = [
        'PROCUREMENT' => ['procurementRequest', ProcurementRequest::class],
        'ASSIGNMENT' => ['assignmentRequest', AssignmentRequest::class],
        'LOAN' => ['loanRequest', AssetLoanRequest::class],
        'LOAN_EXTENSION' => ['loanExtensionRequest', LoanExtensionRequest::class],
        'DISPOSAL' => ['disposalRequest', DisposalRequest::class],
    ];

    public function test_subject_relation_untuk_setiap_tipe_menunjuk_relasi_has_one_yang_benar(): void
    {
        foreach (ApprovalType::cases() as $type) {
            [$relation, $class] = self::SUBJECTS[$type->value];
            $this->assertSame($relation, ApprovalRequest::subjectRelation($type));

            $instance = (new ApprovalRequest)->{$relation}();
            $this->assertInstanceOf(HasOne::class, $instance);
            $this->assertInstanceOf($class, $instance->getRelated());
            $this->assertInstanceOf(Approvable::class, $instance->getRelated());
            $this->assertSame($type, $instance->getRelated()->approvalType(), 'approvalType() model sumber harus konsisten');
        }
    }

    public function test_subject_mengembalikan_permintaan_sumber_per_tipe(): void
    {
        $loan = $this->makeLoan(5);
        $subjects = [
            [ApprovalType::Procurement, $this->makeProcurement(5, ProcurementStatus::PendingApproval)],
            [ApprovalType::Assignment, $this->makeAssignmentRequest(5, 5, RequestStatus::PendingApproval)],
            [ApprovalType::Loan, $this->makeLoanRequest(5, 5, RequestStatus::PendingApproval)],
            [ApprovalType::LoanExtension, $this->makeExtension($loan, 5)],
            [ApprovalType::Disposal, $this->makeDisposal(5, DisposalStatus::PendingApproval)],
        ];

        foreach ($subjects as [$type, $model]) {
            $step = $this->attachApproval($model, $type, 4);
            $subject = $step->approvalRequest->fresh()->subject();

            $this->assertInstanceOf($model::class, $subject, $type->value);
            $this->assertTrue($subject->is($model), $type->value);
            $this->assertSame($type, $step->approvalRequest->request_type);
        }
    }

    public function test_subject_null_bila_permintaan_sumber_tidak_ada(): void
    {
        $approval = ApprovalRequest::create([
            'request_type' => ApprovalType::Disposal, 'requester_employee_id' => 5, 'status' => 'PENDING',
        ]);

        $this->assertNull($approval->subject());
    }

    public function test_steps_diurutkan_menurut_step_order(): void
    {
        $request = $this->makeProcurement(5, ProcurementStatus::PendingApproval);
        $step1 = $this->attachApproval($request, ApprovalType::Procurement, 4);
        $step2 = \App\Models\ApprovalStep::create([
            'approval_request_id' => $step1->approval_request_id, 'step_order' => 2,
            'approver_employee_id' => 1, 'approver_source' => 'ESCALATION', 'status' => 'PENDING',
        ]);

        $this->assertSame([$step1->id, $step2->id], $step1->approvalRequest->steps->pluck('id')->all());
        $this->assertSame($step1->id, $request->fresh()->pendingStep()?->id);
    }

    public function test_pending_step_null_tanpa_approval_atau_bila_sudah_diputuskan(): void
    {
        $this->assertNull($this->makeProcurement(5)->pendingStep());

        $request = $this->makeProcurement(5, ProcurementStatus::Approved);
        $this->attachApproval($request, ApprovalType::Procurement, 4, \App\Enums\ApprovalStatus::Approved);
        $this->assertNull($request->fresh()->pendingStep());
    }

    public function test_approval_request_final_tidak_boleh_berubah_status(): void
    {
        $approval = ApprovalRequest::create([
            'request_type' => ApprovalType::Procurement, 'requester_employee_id' => 5, 'status' => 'REJECTED',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $approval->update(['status' => 'APPROVED']);
    }

    public function test_extension_status_default_dan_cast(): void
    {
        $extension = $this->makeExtension($this->makeLoan(5), 5)->fresh();
        $this->assertSame(ExtensionStatus::PendingApproval, $extension->status);
        $this->assertTrue($extension->requested_due_at->greaterThan($extension->current_due_at));
    }
}
