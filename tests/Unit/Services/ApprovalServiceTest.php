<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\EmploymentStatus;
use App\Enums\ProcurementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\ProcurementRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\ApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit test ApprovalService: submit, resolusi approver (termasuk eskalasi), keputusan, dan pembatalan.
 * Subjek approval memakai ProcurementRequest karena handler-nya paling sederhana.
 */
class ApprovalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->service = app(ApprovalService::class);
    }

    // ---------------------------------------------------------------- helpers

    private function demoUser(string $name): User
    {
        return User::where('email', "{$name}@kantorku.test")->firstOrFail();
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        $suffix = uniqid();

        return Employee::create(array_merge([
            'employee_no' => 'UT1-'.$suffix,
            'name' => 'Karyawan Uji '.$suffix,
            'email' => "ut1-{$suffix}@example.test",
            'department_id' => 2,
            'employment_status' => EmploymentStatus::Active,
        ], $attributes));
    }

    /** @param  array<string>  $roles */
    private function makeUser(Employee $employee, array $roles = ['staff'], bool $active = true): User
    {
        $user = User::create([
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'email' => 'user-'.$employee->email,
            'password' => 'password',
            'is_active' => $active,
        ]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    private function makeProcurement(Employee $requester, ?User $creator = null, ProcurementStatus $status = ProcurementStatus::PendingApproval): ProcurementRequest
    {
        return ProcurementRequest::create([
            'request_no' => 'UT1-PR-'.uniqid(),
            'requester_employee_id' => $requester->id,
            'created_by_user_id' => $creator?->id,
            'department_id' => $requester->department_id,
            'title' => 'Pengadaan uji',
            'justification' => 'Untuk unit test',
            'estimated_total' => 0,
            'status' => $status,
        ]);
    }

    /** Ajukan procurement staff (atasan: manager.it) dan kembalikan step pertamanya. */
    private function submitStaffProcurement(): array
    {
        $staff = $this->demoUser('staff');
        $pr = $this->makeProcurement($staff->employee, $staff);
        $approval = DB::transaction(fn () => $this->service->submit($pr, $staff->employee, $staff));

        return [$pr, $approval, $approval->steps()->firstOrFail()];
    }

    private function setEscalation(mixed $employeeId): void
    {
        Setting::put('approval.escalation_employee_id', $employeeId);
    }

    // ----------------------------------------------------------------- submit

    public function test_submit_membuat_approval_request_step_manager_dan_menautkan_subjek(): void
    {
        $staff = $this->demoUser('staff');
        $pr = $this->makeProcurement($staff->employee, $staff);

        $approval = DB::transaction(fn () => $this->service->submit($pr, $staff->employee, $staff));

        $approval->refresh();
        $this->assertSame(ApprovalType::Procurement, $approval->request_type);
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame($staff->employee_id, $approval->requester_employee_id);
        $this->assertSame($staff->id, $approval->submitted_by_user_id);
        $this->assertNotNull($approval->submitted_at);
        $this->assertNull($approval->closed_at);

        $steps = $approval->steps()->get();
        $this->assertCount(1, $steps);
        $this->assertSame(1, $steps[0]->step_order);
        $this->assertSame(4, $steps[0]->approver_employee_id);
        $this->assertSame(ApprovalStep::SOURCE_MANAGER, $steps[0]->approver_source);
        $this->assertSame(ApprovalStatus::Pending, $steps[0]->status);
        $this->assertNull($steps[0]->decided_at);

        $this->assertSame($approval->id, $pr->fresh()->approval_request_id);
        $this->assertSame($pr->id, $approval->subject()->id);
    }

    public function test_submit_mengirim_notifikasi_ke_approver(): void
    {
        [$pr] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');

        Notification::assertSentTo($manager, AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan approval baru'
            && str_contains($n->message, $pr->request_no)
            && $n->url === route('approvals.index'));
        Notification::assertNotSentTo($this->demoUser('staff'), AppNotification::class);
        Notification::assertNotSentTo($this->demoUser('direktur'), AppNotification::class);
    }

    public function test_submit_oleh_admin_atas_nama_requester_mencatat_pengaju(): void
    {
        $staff = $this->demoUser('staff');
        $admin = $this->demoUser('admin.aset');
        $pr = $this->makeProcurement($staff->employee, $admin);

        $approval = DB::transaction(fn () => $this->service->submit($pr, $staff->employee, $admin));

        $this->assertSame($staff->employee_id, $approval->requester_employee_id);
        $this->assertSame($admin->id, $approval->submitted_by_user_id);
        $this->assertSame(4, $approval->steps()->first()->approver_employee_id);
    }

    public static function inactiveStatuses(): array
    {
        return [
            'cuti' => [EmploymentStatus::OnLeave],
            'nonaktif' => [EmploymentStatus::Inactive],
        ];
    }

    #[DataProvider('inactiveStatuses')]
    public function test_submit_ditolak_bila_requester_tidak_aktif(EmploymentStatus $status): void
    {
        $employee = $this->makeEmployee(['manager_employee_id' => 4, 'employment_status' => $status]);
        $user = $this->makeUser($employee);
        $pr = $this->makeProcurement($employee, $user);
        $before = ApprovalRequest::count();

        try {
            DB::transaction(fn () => $this->service->submit($pr, $employee, $user));
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('nonaktif', $e->getMessage());
        }

        $this->assertSame($before, ApprovalRequest::count());
        $this->assertNull($pr->fresh()->approval_request_id);
        Notification::assertNothingSent();
    }

    public function test_submit_gagal_tanpa_approver_valid_tidak_meninggalkan_data(): void
    {
        $this->setEscalation(null);
        $employee = $this->makeEmployee(); // tanpa atasan
        $user = $this->makeUser($employee);
        $pr = $this->makeProcurement($employee, $user);
        $before = ApprovalRequest::count();

        $this->expectException(BusinessRuleException::class);
        try {
            DB::transaction(fn () => $this->service->submit($pr, $employee, $user));
        } finally {
            $this->assertSame($before, ApprovalRequest::count());
            Notification::assertNothingSent();
        }
    }

    public function test_approver_disimpan_sebagai_snapshot_saat_submit(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();

        // Struktur organisasi berubah setelah submit: atasan staff kini direktur
        $this->demoUser('staff')->employee->update(['manager_employee_id' => 1]);

        $this->assertSame(4, $step->fresh()->approver_employee_id);
        $this->expectException(AuthorizationException::class);
        $this->service->decide($step, $this->demoUser('direktur'), true, null);
    }

    // -------------------------------------------------------- resolveApprover

    public function test_resolve_approver_memakai_atasan_langsung_yang_valid(): void
    {
        $staff = $this->demoUser('staff')->employee;

        [$approver, $source] = $this->service->resolveApprover($staff, $staff);

        $this->assertSame(4, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_MANAGER, $source);
    }

    public function test_resolve_approver_memakai_atasan_subjek_bukan_atasan_requester(): void
    {
        // Admin aset (atasan: direktur) mengajukan untuk staff (atasan: manager.it)
        $staff = $this->demoUser('staff')->employee;
        $admin = $this->demoUser('admin.aset')->employee;

        [$approver, $source] = $this->service->resolveApprover($staff, $admin);

        $this->assertSame(4, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_MANAGER, $source);
    }

    public function test_eskalasi_bila_atasan_kosong(): void
    {
        $employee = $this->makeEmployee();

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    #[DataProvider('inactiveStatuses')]
    public function test_eskalasi_bila_atasan_tidak_aktif(EmploymentStatus $status): void
    {
        $manager = $this->makeEmployee(['employment_status' => $status]);
        $this->makeUser($manager, ['manager']);
        $employee = $this->makeEmployee(['manager_employee_id' => $manager->id]);

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public function test_eskalasi_bila_atasan_tidak_memiliki_akun(): void
    {
        // Dimas (emp 7) tidak memiliki user
        $employee = $this->makeEmployee(['manager_employee_id' => 7]);

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public function test_eskalasi_bila_akun_atasan_nonaktif(): void
    {
        $manager = $this->makeEmployee();
        $this->makeUser($manager, ['manager'], active: false);
        $employee = $this->makeEmployee(['manager_employee_id' => $manager->id]);

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public function test_eskalasi_bila_akun_atasan_tanpa_permission_approval(): void
    {
        // Atasan hanya ber-role staff (tanpa approval.decide)
        $manager = $this->makeEmployee();
        $this->makeUser($manager, ['staff', 'asset_admin']);
        $employee = $this->makeEmployee(['manager_employee_id' => $manager->id]);

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public function test_eskalasi_bila_atasan_subjek_sama_dengan_requester(): void
    {
        // manager.it (emp 4) mengajukan untuk staff yang atasannya dirinya sendiri
        $staff = $this->demoUser('staff')->employee;
        $managerIt = $this->demoUser('manager.it')->employee;

        [$approver, $source] = $this->service->resolveApprover($staff, $managerIt);

        $this->assertSame(1, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public function test_submit_dengan_eskalasi_mencatat_sumber_escalation_dan_notifikasi_ke_eskalasi(): void
    {
        $employee = $this->makeEmployee();
        $user = $this->makeUser($employee);
        $pr = $this->makeProcurement($employee, $user);

        $approval = DB::transaction(fn () => $this->service->submit($pr, $employee, $user));

        $step = $approval->steps()->first();
        $this->assertSame(1, $step->approver_employee_id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $step->approver_source);
        Notification::assertSentTo($this->demoUser('direktur'), AppNotification::class);
    }

    public function test_eskalasi_memakai_karyawan_yang_dikonfigurasi(): void
    {
        $this->setEscalation(4);
        $employee = $this->makeEmployee();

        [$approver, $source] = $this->service->resolveApprover($employee, $employee);

        $this->assertSame(4, $approver->id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $source);
    }

    public static function invalidEscalations(): array
    {
        return [
            'tidak dikonfigurasi (null)' => [null],
            'string kosong' => [''],
            'karyawan tidak ada' => ['999999'],
            'karyawan tanpa akun' => ['7'],
            'karyawan tanpa permission approval' => ['5'],
        ];
    }

    #[DataProvider('invalidEscalations')]
    public function test_gagal_bila_eskalasi_tidak_valid(?string $escalation): void
    {
        $this->setEscalation($escalation);
        $employee = $this->makeEmployee();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Tidak ditemukan approver yang valid');
        $this->service->resolveApprover($employee, $employee);
    }

    public function test_gagal_bila_karyawan_eskalasi_nonaktif(): void
    {
        $escalation = $this->makeEmployee(['employment_status' => EmploymentStatus::Inactive]);
        $this->makeUser($escalation, ['manager']);
        $this->setEscalation($escalation->id);
        $employee = $this->makeEmployee();

        $this->expectException(BusinessRuleException::class);
        $this->service->resolveApprover($employee, $employee);
    }

    public function test_gagal_bila_eskalasi_sama_dengan_requester(): void
    {
        // Direktur (emp 1, tanpa atasan) adalah approver eskalasi itu sendiri
        $direktur = $this->demoUser('direktur')->employee;

        $this->expectException(BusinessRuleException::class);
        $this->service->resolveApprover($direktur, $direktur);
    }

    // ----------------------------------------------------------------- decide

    public function test_approve_menutup_approval_menjalankan_handler_dan_memberi_notifikasi(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');

        $result = $this->service->decide($step, $manager, true, '  Setuju  ');

        $this->assertSame($approval->id, $result->id);
        $step->refresh();
        $this->assertSame(ApprovalStatus::Approved, $step->status);
        $this->assertSame('Setuju', $step->comment);
        $this->assertNotNull($step->decided_at);
        $this->assertSame($manager->id, $step->decided_by_user_id);

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Approved, $approval->status);
        $this->assertNotNull($approval->closed_at);
        $this->assertSame(ProcurementStatus::Approved, $pr->fresh()->status);

        Notification::assertSentTo($this->demoUser('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan disetujui'
            && str_contains($n->message, 'telah disetujui')
            && $n->url === route('procurements.show', $pr));
    }

    public function test_approve_tanpa_komentar_menyimpan_null(): void
    {
        [, , $step] = $this->submitStaffProcurement();

        $this->service->decide($step, $this->demoUser('manager.it'), true, '   ');

        $this->assertNull($step->fresh()->comment);
        $this->assertSame(ApprovalStatus::Approved, $step->fresh()->status);
    }

    public function test_komentar_nol_tidak_dianggap_kosong(): void
    {
        [, , $step] = $this->submitStaffProcurement();

        $this->service->decide($step, $this->demoUser('manager.it'), false, '0');

        $this->assertSame('0', $step->fresh()->comment);
        $this->assertSame(ApprovalStatus::Rejected, $step->fresh()->status);
    }

    public function test_reject_menutup_approval_menjalankan_handler_dan_memberi_notifikasi_alasan(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');

        $this->service->decide($step, $manager, false, 'Anggaran habis');

        $step->refresh();
        $this->assertSame(ApprovalStatus::Rejected, $step->status);
        $this->assertSame('Anggaran habis', $step->comment);
        $this->assertSame($manager->id, $step->decided_by_user_id);
        $approval->refresh();
        $this->assertSame(ApprovalStatus::Rejected, $approval->status);
        $this->assertNotNull($approval->closed_at);
        $this->assertSame(ProcurementStatus::Rejected, $pr->fresh()->status);

        Notification::assertSentTo($this->demoUser('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan ditolak'
            && str_contains($n->message, 'ditolak: Anggaran habis'));
    }

    public static function blankComments(): array
    {
        return ['null' => [null], 'kosong' => [''], 'spasi' => ["  \n\t "]];
    }

    #[DataProvider('blankComments')]
    public function test_reject_tanpa_alasan_ditolak(?string $comment): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();

        try {
            $this->service->decide($step, $this->demoUser('manager.it'), false, $comment);
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Alasan penolakan wajib diisi', $e->getMessage());
        }

        $this->assertSame(ApprovalStatus::Pending, $step->fresh()->status);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame(ProcurementStatus::PendingApproval, $pr->fresh()->status);
    }

    public static function wrongDeciders(): array
    {
        return [
            'staff tanpa permission' => ['staff'],
            'requester sendiri' => ['staff'],
            'manager lain (bukan approver yang dituju)' => ['direktur'],
            'admin aset' => ['admin.aset'],
            'system admin' => ['sysadmin'],
            'auditor' => ['auditor'],
        ];
    }

    #[DataProvider('wrongDeciders')]
    public function test_hanya_approver_yang_dituju_dapat_memutuskan(string $name): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();

        try {
            $this->service->decide($step, $this->demoUser($name), true, 'OK');
            $this->fail('Seharusnya AuthorizationException');
        } catch (AuthorizationException) {
        }

        $this->assertSame(ApprovalStatus::Pending, $step->fresh()->status);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame(ProcurementStatus::PendingApproval, $pr->fresh()->status);
    }

    public function test_approver_yang_akunnya_dinonaktifkan_tidak_dapat_memutuskan(): void
    {
        [, , $step] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');
        $manager->update(['is_active' => false]);

        $this->expectException(AuthorizationException::class);
        $this->service->decide($step, $manager, true, null);
    }

    public function test_approver_yang_kehilangan_permission_tidak_dapat_memutuskan(): void
    {
        [, , $step] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');
        $manager->roles()->sync(Role::where('name', 'staff')->pluck('id'));
        $manager->flushPermissionCache();

        $this->expectException(AuthorizationException::class);
        $this->service->decide($step, $manager, true, null);
    }

    public function test_database_menolak_step_dengan_approver_sama_dengan_requester(): void
    {
        // Lapisan pertahanan terakhir untuk self-approval: trigger approval_steps
        $staff = $this->demoUser('staff');
        $approval = ApprovalRequest::create([
            'request_type' => ApprovalType::Procurement,
            'requester_employee_id' => 4,
            'submitted_by_user_id' => $staff->id,
            'status' => ApprovalStatus::Pending,
            'submitted_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Approver tidak boleh sama dengan requester');
        DB::transaction(fn () => $approval->steps()->create([
            'step_order' => 1, 'approver_employee_id' => 4,
            'approver_source' => ApprovalStep::SOURCE_MANAGER, 'status' => ApprovalStatus::Pending,
        ]));
    }

    public function test_keputusan_ganda_ditolak(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();
        $manager = $this->demoUser('manager.it');
        $this->service->decide($step, $manager, true, 'OK');

        try {
            // Objek step yang basi (masih PENDING di memori) tetap ditolak karena divalidasi ulang dari DB
            $this->service->decide($step, $manager, false, 'Berubah pikiran');
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('sudah diputuskan', $e->getMessage());
        }

        $this->assertSame(ApprovalStatus::Approved, $step->fresh()->status);
        $this->assertSame('OK', $step->fresh()->comment);
        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        $this->assertSame(ProcurementStatus::Approved, $pr->fresh()->status);
    }

    public function test_keputusan_atas_approval_yang_dibatalkan_ditolak(): void
    {
        [, $approval, $step] = $this->submitStaffProcurement();
        $this->service->cancel($approval);

        $this->expectException(BusinessRuleException::class);
        $this->service->decide($step, $this->demoUser('manager.it'), true, null);
    }

    public function test_approve_step_pertama_dari_multi_step_belum_menutup_approval(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();
        $approval->steps()->create([
            'step_order' => 2, 'approver_employee_id' => 1,
            'approver_source' => ApprovalStep::SOURCE_ESCALATION, 'status' => ApprovalStatus::Pending,
        ]);

        $this->service->decide($step, $this->demoUser('manager.it'), true, null);

        $this->assertSame(ApprovalStatus::Approved, $step->fresh()->status);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertNull($approval->fresh()->closed_at);
        $this->assertSame(ProcurementStatus::PendingApproval, $pr->fresh()->status);

        $last = $approval->steps()->where('step_order', 2)->firstOrFail();
        $this->service->decide($last, $this->demoUser('direktur'), true, null);

        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        $this->assertSame(ProcurementStatus::Approved, $pr->fresh()->status);
    }

    // ----------------------------------------------------------------- cancel

    public function test_cancel_membatalkan_approval_dan_step_pending(): void
    {
        [$pr, $approval, $step] = $this->submitStaffProcurement();

        $this->service->cancel($approval);

        $this->assertSame(ApprovalStatus::Cancelled, $step->fresh()->status);
        $approval->refresh();
        $this->assertSame(ApprovalStatus::Cancelled, $approval->status);
        $this->assertNotNull($approval->closed_at);
        // Status permintaan domain diurus oleh service pemiliknya, bukan ApprovalService
        $this->assertSame(ProcurementStatus::PendingApproval, $pr->fresh()->status);
    }

    public function test_cancel_null_tidak_melakukan_apa_pun(): void
    {
        $this->service->cancel(null);
        $this->assertTrue(true);
    }

    public function test_cancel_approval_yang_sudah_final_tidak_mengubah_apa_pun(): void
    {
        [, $approval, $step] = $this->submitStaffProcurement();
        $this->service->decide($step, $this->demoUser('manager.it'), true, null);
        $closedAt = $approval->fresh()->closed_at;

        $this->service->cancel($approval->fresh());

        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        $this->assertEquals($closedAt, $approval->fresh()->closed_at);
        $this->assertSame(ApprovalStatus::Approved, $step->fresh()->status);
    }

    public function test_cancel_tidak_mengubah_step_yang_sudah_diputuskan(): void
    {
        [, $approval, $step] = $this->submitStaffProcurement();
        $second = $approval->steps()->create([
            'step_order' => 2, 'approver_employee_id' => 1,
            'approver_source' => ApprovalStep::SOURCE_ESCALATION, 'status' => ApprovalStatus::Pending,
        ]);
        $this->service->decide($step, $this->demoUser('manager.it'), true, null);

        $this->service->cancel($approval->fresh());

        $this->assertSame(ApprovalStatus::Approved, $step->fresh()->status);
        $this->assertSame(ApprovalStatus::Cancelled, $second->fresh()->status);
        $this->assertSame(ApprovalStatus::Cancelled, $approval->fresh()->status);
    }
}
