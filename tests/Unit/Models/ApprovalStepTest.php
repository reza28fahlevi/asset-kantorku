<?php

namespace Tests\Unit\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\ProcurementStatus;
use App\Models\ApprovalStep;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class ApprovalStepTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_is_pending_hanya_untuk_status_pending(): void
    {
        foreach (ApprovalStatus::cases() as $status) {
            $this->assertSame($status === ApprovalStatus::Pending, new ApprovalStep(['status' => $status])->isPending(), $status->value);
        }
    }

    public function test_relasi_approver_dan_cast(): void
    {
        $step = $this->attachApproval($this->makeProcurement(5, ProcurementStatus::PendingApproval), ApprovalType::Procurement, 4)->fresh();

        $this->assertSame('Dewi Lestari', $step->approver->name);
        $this->assertSame(1, $step->step_order);
        $this->assertSame(ApprovalStatus::Pending, $step->status);
        $this->assertSame(ApprovalStep::SOURCE_MANAGER, $step->approver_source);
    }

    public function test_keputusan_step_bersifat_final_di_database(): void
    {
        $step = $this->attachApproval($this->makeProcurement(5, ProcurementStatus::PendingApproval), ApprovalType::Procurement, 4, ApprovalStatus::Approved);

        $this->expectException(QueryException::class);
        $step->update(['status' => ApprovalStatus::Rejected, 'comment' => 'ubah keputusan']);
    }

    public function test_approver_tidak_boleh_sama_dengan_requester_di_database(): void
    {
        $this->expectException(QueryException::class);
        $this->attachApproval($this->makeProcurement(4, ProcurementStatus::PendingApproval), ApprovalType::Procurement, 4);
    }
}
