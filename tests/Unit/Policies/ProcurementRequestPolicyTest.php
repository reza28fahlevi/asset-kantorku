<?php

namespace Tests\Unit\Policies;

use App\Enums\ProcurementStatus;
use App\Models\ProcurementRequest;
use App\Policies\ProcurementRequestPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class ProcurementRequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private function allows(string $user, string $ability, ProcurementRequest $request): bool
    {
        return Gate::forUser($this->user($user))->allows($ability, $request);
    }

    public function test_policy_terdaftar_untuk_model_procurement(): void
    {
        $this->assertInstanceOf(ProcurementRequestPolicy::class, Gate::getPolicyFor(ProcurementRequest::class));
    }

    public function test_update_hanya_draft_milik_sendiri(): void
    {
        $draft = $this->makeProcurement(5, ProcurementStatus::Draft, $this->user('staff')->id);

        $this->assertTrue($this->allows('staff', 'update', $draft));
        $this->assertFalse($this->allows('admin.aset', 'update', $draft));
        $this->assertFalse($this->allows('manager.it', 'update', $draft));

        foreach (ProcurementStatus::cases() as $status) {
            if ($status === ProcurementStatus::Draft) {
                continue;
            }
            $request = $this->makeProcurement(5, $status, $this->user('staff')->id);
            $this->assertFalse($this->allows('staff', 'update', $request), "pengajuan {$status->value} tidak boleh diubah");
        }
    }

    public function test_order_hanya_saat_approved_dan_oleh_pemilik_permission_order(): void
    {
        foreach (ProcurementStatus::cases() as $status) {
            $request = $this->makeProcurement(5, $status);
            $this->assertSame($status === ProcurementStatus::Approved, $this->allows('admin.aset', 'order', $request), $status->value);
        }

        $approved = $this->makeProcurement(5, ProcurementStatus::Approved, $this->user('staff')->id);
        foreach (['staff', 'manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse($this->allows($name, 'order', $approved), $name);
        }
    }

    public function test_receive_hanya_saat_ordered_atau_partially_received(): void
    {
        foreach (ProcurementStatus::cases() as $status) {
            $request = $this->makeProcurement(5, $status);
            $expected = in_array($status, [ProcurementStatus::Ordered, ProcurementStatus::PartiallyReceived], true);
            $this->assertSame($expected, $this->allows('admin.aset', 'receive', $request), $status->value);
        }

        $ordered = $this->makeProcurement(5, ProcurementStatus::Ordered, $this->user('staff')->id);
        foreach (['staff', 'manager.it', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse($this->allows($name, 'receive', $ordered), $name);
        }
    }

    public function test_close_hanya_untuk_penerimaan_parsial(): void
    {
        foreach (ProcurementStatus::cases() as $status) {
            $request = $this->makeProcurement(5, $status);
            $this->assertSame($status === ProcurementStatus::PartiallyReceived, $this->allows('admin.aset', 'close', $request), $status->value);
        }
        $partial = $this->makeProcurement(5, ProcurementStatus::PartiallyReceived, $this->user('staff')->id);
        $this->assertFalse($this->allows('staff', 'close', $partial));
        $this->assertFalse($this->allows('auditor', 'close', $partial));
    }
}
