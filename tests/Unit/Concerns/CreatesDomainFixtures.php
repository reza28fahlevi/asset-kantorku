<?php

namespace Tests\Unit\Concerns;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\ExtensionStatus;
use App\Enums\LoanStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\Employee;
use App\Models\LoanExtensionRequest;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Pembuat data uji langsung via Eloquent (tanpa service) untuk unit test policy & model.
 * Aset dibuat baru per test agar tidak berebut lock dengan test paralel lain di database yang sama.
 *
 * Karyawan demo: 1 direktur, 2 sysadmin, 3 admin.aset, 4 manager.it, 5 staff, 6 auditor, 7 Dimas (tanpa akun).
 */
trait CreatesDomainFixtures
{
    protected function user(string $name): User
    {
        return User::where('email', "{$name}@kantorku.test")->firstOrFail();
    }

    protected function uniq(string $prefix): string
    {
        return $prefix.'-'.strtoupper(Str::random(10));
    }

    protected function makeEmployee(array $attrs = []): Employee
    {
        return Employee::create($attrs + [
            'employee_no' => $this->uniq('UT3'),
            'name' => 'Karyawan Uji',
            'department_id' => 2,
            'employment_status' => 'ACTIVE',
        ]);
    }

    /** @param  list<string>  $roles */
    protected function makeUser(?int $employeeId, array $roles = [], bool $active = true): User
    {
        $user = User::create([
            'employee_id' => $employeeId,
            'name' => 'User Uji',
            'email' => strtolower($this->uniq('ut3')).'@kantorku.test',
            'password' => 'password',
            'is_active' => $active,
        ]);
        if ($roles !== []) {
            $user->roles()->attach(Role::whereIn('name', $roles)->pluck('id'));
        }

        return $user->fresh();
    }

    protected function makeAsset(array $attrs = []): Asset
    {
        return Asset::create($attrs + [
            'asset_tag' => $this->uniq('UT3'),
            'name' => 'Aset Uji',
            'asset_category_id' => 8,
            'location_id' => 1,
            'status' => AssetStatus::Available,
            'condition' => 'GOOD',
        ]);
    }

    protected function makeProcurement(int $requesterEmployeeId, ProcurementStatus $status = ProcurementStatus::Draft, ?int $createdByUserId = null): ProcurementRequest
    {
        return ProcurementRequest::create([
            'request_no' => $this->uniq('UPR'),
            'requester_employee_id' => $requesterEmployeeId,
            'created_by_user_id' => $createdByUserId,
            'department_id' => 2,
            'title' => 'Pengadaan uji',
            'justification' => 'Kebutuhan uji',
            'status' => $status,
        ]);
    }

    protected function makeAssignmentRequest(int $requesterEmployeeId, int $recipientEmployeeId, RequestStatus $status = RequestStatus::Draft, ?int $createdByUserId = null): AssignmentRequest
    {
        return AssignmentRequest::create([
            'request_no' => $this->uniq('UAS'),
            'requester_employee_id' => $requesterEmployeeId,
            'created_by_user_id' => $createdByUserId,
            'recipient_employee_id' => $recipientEmployeeId,
            'location_id' => 1,
            'purpose' => 'Tujuan uji',
            'start_date' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    protected function makeLoanRequest(int $requesterEmployeeId, int $borrowerEmployeeId, RequestStatus $status = RequestStatus::Draft, ?int $createdByUserId = null): AssetLoanRequest
    {
        return AssetLoanRequest::create([
            'request_no' => $this->uniq('ULN'),
            'requester_employee_id' => $requesterEmployeeId,
            'created_by_user_id' => $createdByUserId,
            'borrower_employee_id' => $borrowerEmployeeId,
            'purpose' => 'Tujuan uji',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => $status,
        ]);
    }

    protected function makeDisposal(int $requesterEmployeeId, DisposalStatus $status = DisposalStatus::Draft, ?int $createdByUserId = null, ?Asset $asset = null): DisposalRequest
    {
        return DisposalRequest::create([
            'request_no' => $this->uniq('UDS'),
            'asset_id' => ($asset ?? $this->makeAsset())->id,
            'requester_employee_id' => $requesterEmployeeId,
            'created_by_user_id' => $createdByUserId,
            'reason_type' => 'DAMAGED',
            'reason' => 'Rusak berat',
            'planned_method' => 'SCRAP',
            'status' => $status,
        ]);
    }

    protected function makeLoan(int $borrowerEmployeeId, LoanStatus $status = LoanStatus::CheckedOut, ?AssetLoanRequest $request = null, ?\DateTimeInterface $dueAt = null, ?Asset $asset = null): AssetLoan
    {
        $checkedOut = now()->subDays(5)->startOfMinute();
        $dueAt ??= now()->addDays(5)->startOfMinute();

        return AssetLoan::create([
            'asset_id' => ($asset ?? $this->makeAsset(['status' => AssetStatus::OnLoan]))->id,
            'borrower_employee_id' => $borrowerEmployeeId,
            'asset_loan_request_id' => $request?->id,
            'checked_out_at' => $checkedOut,
            'due_at' => $dueAt,
            'original_due_at' => $dueAt,
            'returned_at' => $status === LoanStatus::Returned ? now() : null,
            'condition_out' => 'GOOD',
            'status' => $status,
        ]);
    }

    protected function makeAssignment(int $employeeId, ?AssignmentRequest $request = null, bool $returned = false, ?Asset $asset = null): AssetAssignment
    {
        return AssetAssignment::create([
            'asset_id' => ($asset ?? $this->makeAsset(['status' => AssetStatus::Assigned]))->id,
            'employee_id' => $employeeId,
            'assignment_request_id' => $request?->id,
            'location_id' => 1,
            'assigned_at' => now()->subDays(3),
            'condition_out' => 'GOOD',
            'returned_at' => $returned ? now() : null,
        ]);
    }

    protected function makeExtension(AssetLoan $loan, int $requesterEmployeeId, ExtensionStatus $status = ExtensionStatus::PendingApproval, ?int $createdByUserId = null): LoanExtensionRequest
    {
        return LoanExtensionRequest::create([
            'request_no' => $this->uniq('UEX'),
            'asset_loan_id' => $loan->id,
            'requester_employee_id' => $requesterEmployeeId,
            'created_by_user_id' => $createdByUserId,
            'current_due_at' => $loan->due_at,
            'requested_due_at' => $loan->due_at->copy()->addDays(3),
            'reason' => 'Masih dipakai',
            'status' => $status,
        ]);
    }

    protected function makeReceipt(ProcurementRequest $procurement): ProcurementReceipt
    {
        return ProcurementReceipt::create([
            'receipt_no' => $this->uniq('URC'),
            'procurement_request_id' => $procurement->id,
            'received_at' => now(),
        ]);
    }

    /** Tautkan approval request + satu step ke permintaan. */
    protected function attachApproval(Model $request, ApprovalType $type, int $approverEmployeeId, ApprovalStatus $stepStatus = ApprovalStatus::Pending): ApprovalStep
    {
        $approval = ApprovalRequest::create([
            'request_type' => $type,
            'requester_employee_id' => $request->requester_employee_id,
            'status' => ApprovalStatus::Pending,
            'submitted_at' => now(),
        ]);
        $request->update(['approval_request_id' => $approval->id]);

        return ApprovalStep::create([
            'approval_request_id' => $approval->id,
            'step_order' => 1,
            'approver_employee_id' => $approverEmployeeId,
            'approver_source' => ApprovalStep::SOURCE_MANAGER,
            'status' => $stepStatus,
            'comment' => $stepStatus === ApprovalStatus::Rejected ? 'Ditolak uji' : null,
            'decided_at' => in_array($stepStatus, [ApprovalStatus::Approved, ApprovalStatus::Rejected], true) ? now() : null,
        ]);
    }
}
