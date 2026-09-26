<?php

namespace Tests\Feature\Concerns;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/** Helper untuk functional test berbasis data demo (database asset_kantorku_test). */
trait ActsAsDemoUsers
{
    protected function user(string $name): User
    {
        return User::where('email', "{$name}@kantorku.test")->firstOrFail();
    }

    /** Aksi berhasil: redirect tanpa error validasi maupun flash error. */
    protected function assertSucceeded(TestResponse $response): TestResponse
    {
        $response->assertSessionHasNoErrors();
        $this->assertNull(session('error'), 'Flash error: '.session('error'));
        $this->assertTrue($response->isRedirect() || $response->isOk(), 'Status '.$response->getStatusCode());

        return $response;
    }

    /** Setujui semua tahap approval yang pending sebagai approver masing-masing. */
    protected function approveAll(?ApprovalRequest $approval): void
    {
        $this->assertNotNull($approval, 'Approval request belum dibuat');
        foreach ($approval->fresh()->steps()->where('status', 'PENDING')->orderBy('step_order')->get() as $step) {
            $approver = User::where('employee_id', $step->approver_employee_id)->firstOrFail();
            $this->assertSucceeded($this->actingAs($approver)->post(route('approvals.approve', $step), ['comment' => 'OK disetujui']));
        }
    }

    /** Halaman dapat dirender. */
    protected function assertPageOk(User $user, string $url): void
    {
        $this->actingAs($user)->get($url)->assertOk();
    }
}
