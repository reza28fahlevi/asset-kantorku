<?php

namespace Tests\Unit\Policies;

use App\Enums\RequestStatus;
use App\Models\AssetLoanRequest;
use App\Policies\AssetLoanRequestPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetLoanRequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_policy_terdaftar_untuk_model_loan_request(): void
    {
        $this->assertInstanceOf(AssetLoanRequestPolicy::class, Gate::getPolicyFor(AssetLoanRequest::class));
    }

    public function test_checkout_hanya_saat_approved_oleh_pemilik_permission_checkout(): void
    {
        $admin = $this->user('admin.aset');
        foreach (RequestStatus::cases() as $status) {
            $request = $this->makeLoanRequest(5, 5, $status, $this->user('staff')->id);
            $this->assertSame($status === RequestStatus::Approved, Gate::forUser($admin)->allows('checkout', $request), $status->value);
        }

        $approved = $this->makeLoanRequest(5, 5, RequestStatus::Approved, $this->user('staff')->id);
        foreach (['staff', 'manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('checkout', $approved), $name);
        }
    }
}
