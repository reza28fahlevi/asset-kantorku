<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AssetAssignment;
use App\Models\AssetLoan;
use App\Models\AssignmentRequest;
use App\Models\Attachment;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AssignmentService;
use App\Services\LoanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;

class AssignmentServiceTest extends ServiceTestCase
{
    private function service(): AssignmentService
    {
        return app(AssignmentService::class);
    }

    private function request(User $user, array $assetIds, array $overrides = [], bool $submit = true): AssignmentRequest
    {
        $this->actingAs($user);

        return $this->service()->create(array_merge([
            'asset_ids' => $assetIds,
            'location_id' => 3,
            'purpose' => 'Laptop kerja harian',
            'start_date' => today()->toDateString(),
        ], $overrides), $user, $submit)->fresh();
    }

    private function handover(AssignmentRequest $request, array $overrides = []): void
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $this->service()->handover($request->fresh(), array_merge([
            'assigned_at' => now()->subMinutes(5)->toDateTimeString(),
            'condition_out' => AssetCondition::Good->value,
        ], $overrides), $admin);
    }

    private function activeAssignment(?\App\Models\Asset $asset = null): AssetAssignment
    {
        $asset ??= $this->makeAsset();
        $request = $this->request($this->user('staff'), [$asset->id]);
        $this->approve($request);
        $this->handover($request);

        return AssetAssignment::where('assignment_request_id', $request->id)->sole();
    }

    private function returnData(array $overrides = []): array
    {
        return array_merge([
            'returned_at' => now()->toDateTimeString(),
            'condition_in' => AssetCondition::Good->value,
            'next_status' => AssetStatus::Available->value,
            'location_id' => 2,
        ], $overrides);
    }

    // ------------------------------------------------------------ create / submit

    public function test_create_draft_untuk_diri_sendiri(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($this->user('staff'), [$asset->id], [], false);

        $this->assertSame(RequestStatus::Draft, $request->status);
        $this->assertMatchesRegularExpression('/^ASG-\d{4}-\d{5}$/', $request->request_no);
        $this->assertSame(5, $request->requester_employee_id);
        $this->assertSame(5, $request->recipient_employee_id);
        $this->assertSame(3, $request->location_id);
        $this->assertNull($request->approval_request_id);
        $this->assertSame([$asset->id], $request->assets()->pluck('assets.id')->all());
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_create_submit_approver_adalah_manager_penerima(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);

        $this->assertSame(RequestStatus::PendingApproval, $request->status);
        $this->assertNotNull($request->submitted_at);
        $this->assertSame(4, $request->approvalRequest->steps()->sole()->approver_employee_id);
        Notification::assertSentTo($this->user('manager.it'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan approval baru');
    }

    public function test_staff_tanpa_izin_tidak_dapat_membuat_untuk_orang_lain(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id], ['recipient_employee_id' => 7], false);

        $this->assertSame(5, $request->recipient_employee_id);
    }

    public function test_manager_tanpa_izin_create_for_others_juga_diabaikan(): void
    {
        $request = $this->request($this->user('manager.it'), [$this->makeAsset()->id], ['recipient_employee_id' => 5], false);

        $this->assertSame(4, $request->recipient_employee_id);
    }

    public function test_admin_membuat_untuk_karyawan_lain_disetujui_manager_penerima(): void
    {
        $request = $this->request($this->user('admin.aset'), [$this->makeAsset()->id], ['recipient_employee_id' => 7]);

        $this->assertSame(7, $request->recipient_employee_id);
        $this->assertSame(3, $request->requester_employee_id);
        $this->assertSame(4, $request->approvalRequest->steps()->sole()->approver_employee_id);
    }

    public function test_penerima_nonaktif_ditolak(): void
    {
        $this->setEmploymentStatus(7, EmploymentStatus::OnLeave);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Penerima');
        $this->request($this->user('admin.aset'), [$this->makeAsset()->id], ['recipient_employee_id' => 7]);
    }

    public function test_aset_tidak_tersedia_ditolak(): void
    {
        foreach ([AssetStatus::Assigned, AssetStatus::OnLoan, AssetStatus::InRepair, AssetStatus::PendingDisposal, AssetStatus::Lost] as $status) {
            $asset = $this->makeAsset(['status' => $status]);
            try {
                $this->request($this->user('staff'), [$asset->id], [], false);
                $this->fail("Aset {$status->value} seharusnya ditolak");
            } catch (BusinessRuleException $e) {
                $this->assertStringContainsString($asset->asset_tag, $e->getMessage());
            }
        }
        $this->assertSame(0, AssignmentRequest::where('created_by_user_id', $this->user('staff')->id)
            ->whereHas('assets', fn ($q) => $q->where('asset_tag', 'like', 'UT2-%'))->count());
    }

    public function test_aset_tidak_ditemukan_ditolak(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak ditemukan');
        $this->request($this->user('staff'), [999999], [], false);
    }

    public function test_submit_hanya_dari_draft(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mengajukan assignment');
        $this->service()->submit($request, $this->user('staff'));
    }

    public function test_submit_ditolak_bila_penerima_menjadi_nonaktif(): void
    {
        $request = $this->request($this->user('admin.aset'), [$this->makeAsset()->id], ['recipient_employee_id' => 7], false);
        $this->setEmploymentStatus(7, EmploymentStatus::Inactive);

        $this->expectException(BusinessRuleException::class);
        $this->service()->submit($request, $this->user('admin.aset'));
    }

    public function test_submit_bentrok_dengan_assignment_terbuka_lain(): void
    {
        $asset = $this->makeAsset();
        $first = $this->request($this->user('staff'), [$asset->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage($first->request_no);
        $this->request($this->user('admin.aset'), [$asset->id], ['start_date' => today()->addMonth()->toDateString()]);
    }

    public function test_submit_bentrok_dengan_peminjaman_yang_belum_selesai(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        $loan = app(LoanService::class)->create([
            'asset_ids' => [$asset->id], 'purpose' => 'Pinjam', 'start_date' => today()->toDateString(), 'due_date' => today()->addDays(5)->toDateString(),
        ], $staff, true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage($loan->request_no);
        $this->request($this->user('admin.aset'), [$asset->id], ['start_date' => today()->addDays(5)->toDateString()]);
    }

    public function test_submit_tidak_bentrok_dengan_peminjaman_yang_selesai_sebelum_tanggal_mulai(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        app(LoanService::class)->create([
            'asset_ids' => [$asset->id], 'purpose' => 'Pinjam', 'start_date' => today()->toDateString(), 'due_date' => today()->addDays(3)->toDateString(),
        ], $staff, true);

        $request = $this->request($this->user('admin.aset'), [$asset->id], ['start_date' => today()->addDays(4)->toDateString()]);
        $this->assertSame(RequestStatus::PendingApproval, $request->status);
    }

    public function test_draft_lain_tidak_menghalangi_submit(): void
    {
        $asset = $this->makeAsset();
        $this->request($this->user('staff'), [$asset->id], [], false);

        $request = $this->request($this->user('admin.aset'), [$asset->id]);
        $this->assertSame(RequestStatus::PendingApproval, $request->status);
    }

    // ------------------------------------------------------------ cancel / approve / reject

    public function test_cancel_draft_dan_pending(): void
    {
        $draft = $this->request($this->user('staff'), [$this->makeAsset()->id], [], false);
        $this->service()->cancel($draft);
        $this->assertSame(RequestStatus::Cancelled, $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->cancelled_at);

        $pending = $this->request($this->user('staff'), [$this->makeAsset()->id]);
        $this->service()->cancel($pending);
        $pending->refresh();
        $this->assertSame(RequestStatus::Cancelled, $pending->status);
        $this->assertSame(ApprovalStatus::Cancelled, $pending->approvalRequest->status);
    }

    public function test_cancel_setelah_disetujui_tanpa_alasan_ditolak(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Alasan pembatalan wajib diisi');
        $this->service()->cancel($request->fresh(), $this->user('staff'), 'abc');
    }

    public function test_cancel_setelah_disetujui_dengan_alasan_oleh_petugas(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);
        $admin = $this->user('admin.aset');

        $this->service()->cancel($request->fresh(), $admin, 'Kebutuhan dibatalkan manajemen');

        $request->refresh();
        $this->assertSame(RequestStatus::Cancelled, $request->status);
        $this->assertSame('Kebutuhan dibatalkan manajemen', $request->cancel_reason);
        $this->assertSame($admin->id, $request->cancelled_by_user_id);
        $this->assertNotNull($request->cancelled_at);
        $this->assertSame('APPROVED', $request->approvalRequest->status->value, 'Keputusan approval tetap tercatat');
        $this->assertDatabaseHas('audit_logs', ['action' => 'cancelled_after_approval', 'auditable_id' => $request->id]);
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan dibatalkan');
    }

    public function test_approve_dan_reject(): void
    {
        $approved = $this->request($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($approved);
        $this->assertSame(RequestStatus::Approved, $approved->fresh()->status);
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Assignment siap diserahterimakan');

        $asset = $this->makeAsset();
        $rejected = $this->request($this->user('staff'), [$asset->id]);
        $this->reject($rejected);
        $this->assertSame(RequestStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    // ------------------------------------------------------------ handover

    public function test_handover_menugaskan_aset_dan_mencatat_histori(): void
    {
        $assets = [$this->makeAsset(['location_id' => 6]), $this->makeAsset(['location_id' => 6])];
        $request = $this->request($this->user('staff'), array_map(fn ($a) => $a->id, $assets));
        $this->approve($request);
        $this->handover($request, ['condition_out' => 'FAIR', 'handover_document_no' => 'BAST-UT-01', 'handover_notes' => 'Charger lengkap', 'attachments' => [$this->pdf()]]);

        $request->refresh();
        $this->assertSame(RequestStatus::Fulfilled, $request->status);
        $this->assertNotNull($request->fulfilled_at);
        foreach ($assets as $asset) {
            $assignment = AssetAssignment::where('asset_id', $asset->id)->sole();
            $this->assertTrue($assignment->isActive());
            $this->assertSame(5, $assignment->employee_id);
            $this->assertSame(3, $assignment->location_id);
            $this->assertSame('BAST-UT-01', $assignment->handover_document_no);
            $this->assertSame(AssetCondition::Fair, $assignment->condition_out);
            $this->assertSame($this->user('admin.aset')->id, $assignment->assigned_by_user_id);
            $this->assertSame(1, Attachment::where('attachable_type', $assignment->getMorphClass())->where('attachable_id', $assignment->id)->where('category', 'HANDOVER')->count());

            $asset->refresh();
            $this->assertSame(AssetStatus::Assigned, $asset->status);
            $this->assertSame(3, $asset->location_id);
            $this->assertSame(AssetCondition::Fair, $asset->condition);
            $event = $this->lastEvent($asset);
            $this->assertSame(AssetEventType::Assigned, $event->event_type);
            $this->assertSame(6, $event->from_location_id);
            $this->assertSame(3, $event->to_location_id);
            $this->assertSame('asset_assignment', $event->reference_type);
            $this->assertSame($assignment->id, $event->reference_id);
        }
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Aset diserahterimakan');
    }

    public function test_handover_selain_approved_ditolak(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('melakukan serah-terima');
        $this->handover($request);
    }

    public function test_handover_ditolak_bila_aset_sedang_dipinjam(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($this->user('staff'), [$asset->id]);
        $this->approve($request);
        $this->setAssetStatus($asset, AssetStatus::OnLoan);

        try {
            $this->handover($request);
            $this->fail('Handover aset yang dipinjam seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Aset tidak tersedia', $e->getMessage());
        }
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(0, AssetAssignment::where('asset_id', $asset->id)->count());
    }

    public function test_handover_ditolak_bila_penerima_nonaktif(): void
    {
        $request = $this->request($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);
        $this->setEmploymentStatus(5, EmploymentStatus::Inactive);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Penerima');
        $this->handover($request);
    }

    public function test_database_mencegah_assignment_aktif_pada_aset_yang_dipinjam(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::OnLoan]);
        AssetLoan::create([
            'asset_id' => $asset->id, 'borrower_employee_id' => 7, 'checked_out_at' => now()->subDay(), 'due_at' => now()->addDay(),
            'original_due_at' => now()->addDay(), 'condition_out' => 'GOOD', 'status' => LoanStatus::CheckedOut,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('sedang dipinjam');
        AssetAssignment::create([
            'asset_id' => $asset->id, 'employee_id' => 5, 'location_id' => 3, 'assigned_at' => now(), 'condition_out' => 'GOOD',
        ]);
    }

    // ------------------------------------------------------------ return

    public function test_pengembalian_menutup_assignment_dan_status_sesuai_inspeksi(): void
    {
        $assignment = $this->activeAssignment();
        $admin = $this->user('admin.aset');
        $this->service()->returnAsset($assignment, $this->returnData([
            'next_status' => 'IN_REPAIR', 'condition_in' => 'DAMAGED', 'return_notes' => 'Layar retak', 'attachments' => [$this->pdf('foto.pdf')],
        ]), $admin);

        $assignment->refresh();
        $this->assertFalse($assignment->isActive());
        $this->assertSame($admin->id, $assignment->returned_by_user_id);
        $this->assertSame(AssetCondition::Damaged, $assignment->condition_in);
        $this->assertSame('Layar retak', $assignment->return_notes);
        $this->assertSame(1, $assignment->attachments()->where('category', 'RETURN')->count());

        $asset = $assignment->asset;
        $this->assertSame(AssetStatus::InRepair, $asset->status);
        $this->assertSame(2, $asset->location_id);
        $this->assertSame(AssetCondition::Damaged, $asset->condition);
        $event = $this->lastEvent($asset);
        $this->assertSame(AssetEventType::AssignmentReturned, $event->event_type);
        $this->assertSame(AssetStatus::Assigned, $event->from_status);
        $this->assertSame(AssetStatus::InRepair, $event->to_status);
        $this->assertSame(5, $event->related_employee_id);
    }

    public function test_aset_yang_dikembalikan_dapat_ditugaskan_ulang(): void
    {
        $assignment = $this->activeAssignment();
        $this->service()->returnAsset($assignment, $this->returnData(), $this->user('admin.aset'));

        $second = $this->activeAssignment($assignment->asset);
        $this->assertTrue($second->isActive());
        $this->assertSame(2, AssetAssignment::where('asset_id', $assignment->asset_id)->count());
    }

    public function test_pengembalian_kedua_kali_ditolak(): void
    {
        $assignment = $this->activeAssignment();
        $this->service()->returnAsset($assignment, $this->returnData(), $this->user('admin.aset'));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sudah ditutup');
        $this->service()->returnAsset($assignment, $this->returnData(), $this->user('admin.aset'));
    }

    public function test_tanggal_kembali_sebelum_serah_terima_ditolak(): void
    {
        $assignment = $this->activeAssignment();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sebelum tanggal serah-terima');
        $this->service()->returnAsset($assignment, $this->returnData(['returned_at' => now()->subDay()->toDateTimeString()]), $this->user('admin.aset'));
    }

    public function test_aset_yang_ditugaskan_tidak_dapat_diajukan_lagi(): void
    {
        $assignment = $this->activeAssignment();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Ditugaskan');
        $this->request($this->user('admin.aset'), [$assignment->asset_id], ['recipient_employee_id' => 7], false);
    }
}
