<?php

namespace Tests\Unit\Models;

use App\Enums\ApprovalType;
use App\Enums\DisposalStatus;
use App\Enums\ExtensionStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\LoanExtensionRequest;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

/** Scope visibleTo / isVisibleTo / isOwnedBy pada model permintaan ber-approval. */
class InteractsWithApprovalTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    /** @param  list<int>  $ids */
    private function visibleIds(string $class, User $user, array $ids): array
    {
        return $class::query()->whereIn($class::query()->qualifyColumn('id'), $ids)->visibleTo($user)->orderBy('id')->pluck('id')->all();
    }

    public function test_scope_visible_to_procurement_membatasi_cakupan_data(): void
    {
        $staff = $this->user('staff');
        $own = $this->makeProcurement(5, ProcurementStatus::Draft, $staff->id);
        $createdForOther = $this->makeProcurement(7, ProcurementStatus::Draft, $staff->id);
        $toApprove = $this->makeProcurement(3, ProcurementStatus::PendingApproval);
        $this->attachApproval($toApprove, ApprovalType::Procurement, 5);
        $others = $this->makeProcurement(3, ProcurementStatus::PendingApproval);
        $ids = [$own->id, $createdForOther->id, $toApprove->id, $others->id];

        $this->assertSame([$own->id, $createdForOther->id, $toApprove->id], $this->visibleIds(ProcurementRequest::class, $staff, $ids));
        $this->assertSame($ids, $this->visibleIds(ProcurementRequest::class, $this->user('auditor'), $ids), 'view_all');
        $this->assertSame([], $this->visibleIds(ProcurementRequest::class, $this->user('sysadmin'), $ids));
    }

    public function test_scope_visible_to_assignment_dan_loan_menyertakan_penerima_atau_peminjam(): void
    {
        $staff = $this->user('staff');
        $asRecipient = $this->makeAssignmentRequest(3, 5, RequestStatus::Approved);
        $notMine = $this->makeAssignmentRequest(3, 7, RequestStatus::Approved);
        $this->assertSame([$asRecipient->id], $this->visibleIds(AssignmentRequest::class, $staff, [$asRecipient->id, $notMine->id]));

        $asBorrower = $this->makeLoanRequest(3, 5, RequestStatus::Approved);
        $loanNotMine = $this->makeLoanRequest(3, 7, RequestStatus::Approved);
        $this->assertSame([$asBorrower->id], $this->visibleIds(AssetLoanRequest::class, $staff, [$asBorrower->id, $loanNotMine->id]));
    }

    public function test_scope_visible_to_disposal_dan_extension(): void
    {
        $staff = $this->user('staff');
        $own = $this->makeDisposal(5, DisposalStatus::Draft, $staff->id);
        $other = $this->makeDisposal(3, DisposalStatus::Draft);
        $this->assertSame([$own->id], $this->visibleIds(DisposalRequest::class, $staff, [$own->id, $other->id]));
        $this->assertSame([$own->id, $other->id], $this->visibleIds(DisposalRequest::class, $this->user('admin.aset'), [$own->id, $other->id]));

        $loan = $this->makeLoan(5);
        $extension = $this->makeExtension($loan, 5, ExtensionStatus::PendingApproval, $staff->id);
        $this->attachApproval($extension, ApprovalType::LoanExtension, 4);
        $this->assertSame([$extension->id], $this->visibleIds(LoanExtensionRequest::class, $this->user('manager.it'), [$extension->id]), 'approver');
        $this->assertSame([], $this->visibleIds(LoanExtensionRequest::class, $this->user('direktur'), [$extension->id]));
    }

    public function test_scope_visible_to_tetap_aman_digabung_dengan_filter_or_lain(): void
    {
        // Kondisi visibleTo harus dibungkus dalam grup agar tidak "bocor" lewat orWhere sebelumnya.
        $staff = $this->user('staff');
        $mine = $this->makeProcurement(5, ProcurementStatus::Draft, $staff->id);
        $other = $this->makeProcurement(3, ProcurementStatus::Draft);

        $ids = ProcurementRequest::query()
            ->where(fn ($q) => $q->where('id', $mine->id)->orWhere('id', $other->id))
            ->visibleTo($staff)->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_is_owned_by_requester_atau_pembuat(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin.aset');

        $byStaff = $this->makeLoanRequest(5, 5, RequestStatus::Draft);
        $this->assertTrue($byStaff->isOwnedBy($staff));
        $this->assertFalse($byStaff->isOwnedBy($admin));

        $byAdminForDimas = $this->makeLoanRequest(7, 7, RequestStatus::Draft, $admin->id);
        $this->assertTrue($byAdminForDimas->isOwnedBy($admin));
        $this->assertFalse($byAdminForDimas->isOwnedBy($staff));
    }

    public function test_is_visible_to_konsisten_dengan_scope(): void
    {
        $request = $this->makeProcurement(5, ProcurementStatus::PendingApproval, $this->user('staff')->id);

        $this->assertTrue($request->isVisibleTo($this->user('staff')));
        $this->assertTrue($request->isVisibleTo($this->user('auditor')));
        $this->assertFalse($request->isVisibleTo($this->user('manager.it')));

        $this->attachApproval($request, ApprovalType::Procurement, 4);
        $this->assertTrue($request->isVisibleTo($this->user('manager.it')));
    }
}
