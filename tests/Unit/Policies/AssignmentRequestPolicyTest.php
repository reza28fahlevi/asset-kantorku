<?php

namespace Tests\Unit\Policies;

use App\Enums\RequestStatus;
use App\Models\AssignmentRequest;
use App\Policies\AssignmentRequestPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssignmentRequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_policy_terdaftar_untuk_model_assignment_request(): void
    {
        $this->assertInstanceOf(AssignmentRequestPolicy::class, Gate::getPolicyFor(AssignmentRequest::class));
    }

    public function test_handover_hanya_saat_approved_oleh_pemilik_permission_handover(): void
    {
        $admin = $this->user('admin.aset');
        foreach (RequestStatus::cases() as $status) {
            $request = $this->makeAssignmentRequest(5, 5, $status, $this->user('staff')->id);
            $this->assertSame($status === RequestStatus::Approved, Gate::forUser($admin)->allows('handover', $request), $status->value);
        }

        $approved = $this->makeAssignmentRequest(5, 5, RequestStatus::Approved, $this->user('staff')->id);
        foreach (['staff', 'manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('handover', $approved), $name);
        }
    }

    public function test_ability_yang_tidak_didefinisikan_ditolak(): void
    {
        // Assignment tidak memiliki alur edit draft; ability yang tidak ada di policy harus ditolak.
        $draft = $this->makeAssignmentRequest(5, 5, RequestStatus::Draft, $this->user('staff')->id);
        $this->assertFalse(Gate::forUser($this->user('staff'))->allows('update', $draft));
        $this->assertFalse(Gate::forUser($this->user('admin.aset'))->allows('delete', $draft));
    }
}
