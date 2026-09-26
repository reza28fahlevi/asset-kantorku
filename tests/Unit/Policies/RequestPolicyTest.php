<?php

namespace Tests\Unit\Policies;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\DisposalStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

/**
 * Aturan bersama RequestPolicy (view/create/submit/cancel) diuji untuk keempat jenis permintaan
 * ber-approval: procurement, assignment, loan, disposal.
 */
class RequestPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private const STAFF_EMP = 5;
    private const MANAGER_IT_EMP = 4;
    private const DIMAS_EMP = 7;

    public static function kinds(): array
    {
        return [
            'procurement' => ['procurement'],
            'assignment' => ['assignment'],
            'loan' => ['loan'],
            'disposal' => ['disposal'],
        ];
    }

    private function statusEnum(string $kind): string
    {
        return match ($kind) {
            'procurement' => ProcurementStatus::class,
            'disposal' => DisposalStatus::class,
            default => RequestStatus::class,
        };
    }

    private function modelClass(string $kind): string
    {
        return match ($kind) {
            'procurement' => ProcurementRequest::class,
            'assignment' => AssignmentRequest::class,
            'loan' => AssetLoanRequest::class,
            'disposal' => DisposalRequest::class,
        };
    }

    private function approvalType(string $kind): ApprovalType
    {
        return match ($kind) {
            'procurement' => ApprovalType::Procurement,
            'assignment' => ApprovalType::Assignment,
            'loan' => ApprovalType::Loan,
            'disposal' => ApprovalType::Disposal,
        };
    }

    /** Buat permintaan; penerima/peminjam default = Dimas (karyawan tanpa akun). */
    private function makeRequest(string $kind, int $requester, string $status = 'DRAFT', ?int $createdBy = null, int $beneficiary = self::DIMAS_EMP): Model
    {
        $status = $this->statusEnum($kind)::from($status);

        return match ($kind) {
            'procurement' => $this->makeProcurement($requester, $status, $createdBy),
            'assignment' => $this->makeAssignmentRequest($requester, $beneficiary, $status, $createdBy),
            'loan' => $this->makeLoanRequest($requester, $beneficiary, $status, $createdBy),
            'disposal' => $this->makeDisposal($requester, $status, $createdBy),
        };
    }

    private function allows(User $user, string $ability, mixed $argument): bool
    {
        return Gate::forUser($user)->allows($ability, $argument);
    }

    // ------------------------------------------------------------------ create

    #[DataProvider('kinds')]
    public function test_create_hanya_untuk_role_dengan_permission_create(string $kind): void
    {
        $class = $this->modelClass($kind);

        foreach (['staff', 'admin.aset', 'manager.it', 'direktur'] as $name) {
            $this->assertTrue($this->allows($this->user($name), 'create', $class), "{$name} boleh membuat {$kind}");
        }
        foreach (['auditor', 'sysadmin'] as $name) {
            $this->assertFalse($this->allows($this->user($name), 'create', $class), "{$name} tidak boleh membuat {$kind}");
        }
        $this->assertFalse($this->allows($this->makeUser(null), 'create', $class), 'user tanpa role');
    }

    // ------------------------------------------------------------------ submit

    #[DataProvider('kinds')]
    public function test_submit_draft_oleh_pemilik_diizinkan(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'DRAFT', $this->user('staff')->id);

        $this->assertTrue($this->allows($this->user('staff'), 'submit', $request));
    }

    #[DataProvider('kinds')]
    public function test_submit_oleh_pembuat_atas_nama_karyawan_lain_diizinkan(string $kind): void
    {
        $admin = $this->user('admin.aset');
        $request = $this->makeRequest($kind, self::DIMAS_EMP, 'DRAFT', $admin->id);

        $this->assertTrue($this->allows($admin, 'submit', $request));
    }

    #[DataProvider('kinds')]
    public function test_submit_ditolak_untuk_bukan_pemilik_walau_punya_view_all(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'DRAFT', $this->user('staff')->id);

        foreach (['admin.aset', 'manager.it', 'auditor', 'direktur', 'sysadmin'] as $name) {
            $this->assertFalse($this->allows($this->user($name), 'submit', $request), $name);
        }
    }

    #[DataProvider('kinds')]
    public function test_submit_ditolak_bila_status_bukan_draft(string $kind): void
    {
        $staff = $this->user('staff');
        foreach (['PENDING_APPROVAL', 'REJECTED', 'APPROVED', 'CANCELLED'] as $status) {
            $request = $this->makeRequest($kind, self::STAFF_EMP, $status, $staff->id);
            $this->assertFalse($this->allows($staff, 'submit', $request), "{$kind} {$status}");
        }
    }

    #[DataProvider('kinds')]
    public function test_submit_ditolak_bila_pemilik_kehilangan_permission_create(string $kind): void
    {
        // Auditor (emp 6) tidak memiliki permission *.create meski tercatat sebagai requester.
        $auditor = $this->user('auditor');
        $request = $this->makeRequest($kind, 6, 'DRAFT', $auditor->id);

        $this->assertTrue($request->isOwnedBy($auditor));
        $this->assertFalse($this->allows($auditor, 'submit', $request));
    }

    // ------------------------------------------------------------------ cancel

    #[DataProvider('kinds')]
    public function test_cancel_diizinkan_pemilik_selama_draft_atau_menunggu_approval(string $kind): void
    {
        $staff = $this->user('staff');
        foreach (['DRAFT', 'PENDING_APPROVAL'] as $status) {
            $request = $this->makeRequest($kind, self::STAFF_EMP, $status, $staff->id);
            $this->assertTrue($this->allows($staff, 'cancel', $request), "{$kind} {$status}");
        }
    }

    #[DataProvider('kinds')]
    public function test_cancel_ditolak_setelah_ditindaklanjuti_atau_final(string $kind): void
    {
        $staff = $this->user('staff');
        $finals = array_diff($this->statusEnum($kind)::values(), ['DRAFT', 'PENDING_APPROVAL', 'APPROVED']);
        foreach ($finals as $status) {
            $request = $this->makeRequest($kind, self::STAFF_EMP, $status, $staff->id);
            $this->assertFalse($this->allows($staff, 'cancel', $request), "{$kind} {$status}");
        }
    }

    #[DataProvider('kinds')]
    public function test_cancel_setelah_disetujui_oleh_pemilik_atau_petugas_penindak_lanjut(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'APPROVED', $this->user('staff')->id);

        $this->assertTrue($this->allows($this->user('staff'), 'cancel', $request), 'pemilik');
        $this->assertTrue($this->allows($this->user('admin.aset'), 'cancel', $request), 'petugas (order/handover/checkout/execute)');
        foreach (['manager.it', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse($this->allows($this->user($name), 'cancel', $request), $name);
        }
    }

    #[DataProvider('kinds')]
    public function test_cancel_ditolak_untuk_bukan_pemilik_termasuk_approver_dan_admin(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'PENDING_APPROVAL', $this->user('staff')->id);
        $this->attachApproval($request, $this->approvalType($kind), self::MANAGER_IT_EMP);

        foreach (['manager.it', 'admin.aset', 'auditor', 'direktur'] as $name) {
            $this->assertFalse($this->allows($this->user($name), 'cancel', $request), $name);
        }
    }

    #[DataProvider('kinds')]
    public function test_cancel_diizinkan_pembuat_atas_nama_orang_lain(string $kind): void
    {
        $admin = $this->user('admin.aset');
        $request = $this->makeRequest($kind, self::DIMAS_EMP, 'PENDING_APPROVAL', $admin->id);

        $this->assertTrue($this->allows($admin, 'cancel', $request));
    }

    // ------------------------------------------------------------------ view

    #[DataProvider('kinds')]
    public function test_view_pemilik_dan_pemegang_view_all(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'PENDING_APPROVAL', $this->user('staff')->id);

        $this->assertTrue($this->allows($this->user('staff'), 'view', $request), 'requester');
        $this->assertTrue($this->allows($this->user('auditor'), 'view', $request), 'auditor view_all');
        $this->assertTrue($this->allows($this->user('admin.aset'), 'view', $request), 'admin view_all');
    }

    #[DataProvider('kinds')]
    public function test_view_ditolak_untuk_user_yang_tidak_terkait(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'PENDING_APPROVAL', $this->user('staff')->id);
        $otherStaff = $this->makeUser($this->makeEmployee()->id, ['staff']);

        $this->assertFalse($this->allows($otherStaff, 'view', $request), 'staff lain');
        $this->assertFalse($this->allows($this->user('direktur'), 'view', $request), 'manager bukan approver');
        $this->assertFalse($this->allows($this->user('sysadmin'), 'view', $request), 'sysadmin');
        $this->assertFalse($this->allows($this->makeUser(null, ['staff']), 'view', $request), 'user tanpa karyawan');
    }

    #[DataProvider('kinds')]
    public function test_view_diizinkan_untuk_approver_yang_ditunjuk_meski_sudah_memutuskan(string $kind): void
    {
        $request = $this->makeRequest($kind, self::STAFF_EMP, 'APPROVED', $this->user('staff')->id);
        $this->attachApproval($request, $this->approvalType($kind), self::MANAGER_IT_EMP, ApprovalStatus::Approved);

        $this->assertTrue($this->allows($this->user('manager.it'), 'view', $request));
        $this->assertFalse($this->allows($this->user('direktur'), 'view', $request));
    }

    #[DataProvider('kinds')]
    public function test_view_diizinkan_untuk_pembuat_tanpa_karyawan(string $kind): void
    {
        $creator = $this->makeUser(null, ['staff']);
        $request = $this->makeRequest($kind, self::DIMAS_EMP, 'DRAFT', $creator->id);

        $this->assertTrue($this->allows($creator, 'view', $request));
        $this->assertTrue($request->isOwnedBy($creator));
    }

    public function test_view_penerima_assignment_dan_peminjam_loan_sebagai_beneficiary(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin.aset');

        $assignment = $this->makeRequest('assignment', 3, 'APPROVED', $admin->id, self::STAFF_EMP);
        $loan = $this->makeRequest('loan', 3, 'APPROVED', $admin->id, self::STAFF_EMP);

        $this->assertTrue($this->allows($staff, 'view', $assignment));
        $this->assertTrue($this->allows($staff, 'view', $loan));
        // penerima bukan pemilik: tidak boleh membatalkan
        $this->assertFalse($this->allows($staff, 'cancel', $this->makeRequest('loan', 3, 'PENDING_APPROVAL', $admin->id, self::STAFF_EMP)));
    }

    public function test_user_tanpa_karyawan_tidak_melihat_permintaan_milik_karyawan_manapun(): void
    {
        // employee_id null tidak boleh cocok dengan kolom karyawan manapun (mis. dengan NULL/0).
        $orphan = $this->makeUser(null, ['staff']);
        $request = $this->makeRequest('procurement', self::STAFF_EMP, 'DRAFT');

        $this->assertFalse($request->isOwnedBy($orphan));
        $this->assertFalse($this->allows($orphan, 'view', $request));
    }
}
