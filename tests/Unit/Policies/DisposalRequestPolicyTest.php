<?php

namespace Tests\Unit\Policies;

use App\Enums\DisposalStatus;
use App\Models\DisposalRequest;
use App\Policies\DisposalRequestPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class DisposalRequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_policy_terdaftar_untuk_model_disposal(): void
    {
        $this->assertInstanceOf(DisposalRequestPolicy::class, Gate::getPolicyFor(DisposalRequest::class));
    }

    public function test_execute_hanya_saat_approved_oleh_pemilik_permission_execute(): void
    {
        $admin = $this->user('admin.aset');
        foreach (DisposalStatus::cases() as $status) {
            $request = $this->makeDisposal(5, $status, $this->user('staff')->id);
            $this->assertSame($status === DisposalStatus::Approved, Gate::forUser($admin)->allows('execute', $request), $status->value);
        }

        $approved = $this->makeDisposal(5, DisposalStatus::Approved, $this->user('staff')->id);
        foreach (['staff', 'manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('execute', $approved), $name);
        }
    }

    public function test_admin_pemohon_tetap_boleh_mengeksekusi_disposal_miliknya_yang_disetujui(): void
    {
        // Eksekusi adalah tindak lanjut administratif; pemisahan tugas dijaga pada tahap approval.
        $admin = $this->user('admin.aset');
        $request = $this->makeDisposal(3, DisposalStatus::Approved, $admin->id);
        $this->assertTrue(Gate::forUser($admin)->allows('execute', $request));
    }
}
