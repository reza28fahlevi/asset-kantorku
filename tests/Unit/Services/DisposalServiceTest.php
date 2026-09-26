<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\DisposalRequest;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\AssetService;
use App\Services\AssignmentService;
use App\Services\DisposalService;
use App\Services\LoanService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;

class DisposalServiceTest extends ServiceTestCase
{
    private function service(): DisposalService
    {
        return app(DisposalService::class);
    }

    private function request(Asset $asset, bool $submit = true, ?User $user = null, array $overrides = []): DisposalRequest
    {
        $user ??= $this->user('staff');
        $this->actingAs($user);

        return $this->service()->create(array_merge([
            'asset_id' => $asset->id,
            'reason_type' => 'DAMAGED',
            'reason' => 'Motherboard rusak total',
            'condition_description' => 'Tidak menyala',
            'planned_method' => 'SCRAP',
        ], $overrides), $user, $submit)->fresh();
    }

    private function executeData(array $overrides = []): array
    {
        return array_merge([
            'executed_at' => today()->toDateString(),
            'actual_method' => 'SALE',
            'disposal_recipient' => 'CV Barang Bekas',
            'proceeds_amount' => 750000,
            'execution_notes' => 'Dijual sesuai BA',
        ], $overrides);
    }

    private function execute(DisposalRequest $request, array $overrides = []): void
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $this->service()->execute($request->fresh(), $this->executeData($overrides), $admin);
    }

    // ------------------------------------------------------------ create / submit

    public function test_create_draft_tidak_mengubah_status_aset(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset, false, null, ['attachments' => [$this->pdf('foto.pdf')]]);

        $this->assertSame(DisposalStatus::Draft, $request->status);
        $this->assertMatchesRegularExpression('/^DSP-\d{4}-\d{5}$/', $request->request_no);
        $this->assertSame(5, $request->requester_employee_id);
        $this->assertNull($request->approval_request_id);
        $this->assertNull($request->asset_status_before);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertSame(1, $request->attachments()->where('category', 'DISPOSAL_EVIDENCE')->count());
    }

    public function test_submit_mengunci_aset_menjadi_pending_disposal(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);

        $this->assertSame(DisposalStatus::PendingApproval, $request->status);
        $this->assertSame(AssetStatus::Available, $request->asset_status_before);
        $this->assertNotNull($request->submitted_at);
        $this->assertSame(AssetStatus::PendingDisposal, $asset->fresh()->status);
        $event = $this->lastEvent($asset);
        $this->assertSame(AssetEventType::DisposalRequested, $event->event_type);
        $this->assertSame(AssetStatus::Available, $event->from_status);
        $this->assertSame(AssetStatus::PendingDisposal, $event->to_status);
        $this->assertSame('disposal_request', $event->reference_type);
        $this->assertSame($request->id, $event->reference_id);
        // Approver = manager pemohon
        $this->assertSame(4, $request->approvalRequest->steps()->sole()->approver_employee_id);
        Notification::assertSentTo($this->user('manager.it'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan approval baru');
    }

    public function test_status_aset_yang_boleh_diajukan_disposal(): void
    {
        foreach ([AssetStatus::Available, AssetStatus::InRepair, AssetStatus::Lost] as $status) {
            $asset = $this->makeAsset(['status' => $status]);
            $request = $this->request($asset);

            $this->assertSame($status, $request->asset_status_before, $status->value);
            $this->assertSame(AssetStatus::PendingDisposal, $asset->fresh()->status);
        }
    }

    public function test_status_aset_yang_tidak_boleh_diajukan_disposal(): void
    {
        foreach ([AssetStatus::Assigned, AssetStatus::OnLoan, AssetStatus::PendingDisposal, AssetStatus::Disposed] as $status) {
            $asset = $this->makeAsset(['status' => $status]);
            try {
                $this->request($asset, false);
                $this->fail("Aset {$status->value} seharusnya tidak dapat diajukan disposal");
            } catch (BusinessRuleException $e) {
                $this->assertStringContainsString($status->label(), $e->getMessage());
            }
            $this->assertSame(0, DisposalRequest::where('asset_id', $asset->id)->count());
        }
    }

    public function test_aset_dengan_permintaan_assignment_terbuka_ditolak(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        app(AssignmentService::class)->create(['asset_ids' => [$asset->id], 'location_id' => 3, 'purpose' => 'Kerja', 'start_date' => today()->toDateString()], $staff, true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('permintaan assignment/peminjaman yang terbuka');
        $this->request($asset, false, $this->user('admin.aset'));
    }

    public function test_aset_dengan_peminjaman_disetujui_ditolak(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        $loan = app(LoanService::class)->create(['asset_ids' => [$asset->id], 'purpose' => 'Pinjam', 'start_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString()], $staff, true);
        $this->approve($loan);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('permintaan assignment/peminjaman yang terbuka');
        $this->request($asset, false, $this->user('admin.aset'));
    }

    public function test_draft_assignment_tidak_menghalangi_disposal(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        app(AssignmentService::class)->create(['asset_ids' => [$asset->id], 'location_id' => 3, 'purpose' => 'Kerja', 'start_date' => today()->toDateString()], $staff, false);

        $this->assertSame(DisposalStatus::PendingApproval, $this->request($asset)->status);
    }

    public function test_disposal_terbuka_ganda_ditolak(): void
    {
        $asset = $this->makeAsset();
        $this->request($asset, false);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sudah memiliki pengajuan disposal yang terbuka');
        $this->request($asset, false, $this->user('admin.aset'));
    }

    public function test_disposal_baru_boleh_setelah_sebelumnya_dibatalkan_atau_ditolak(): void
    {
        $asset = $this->makeAsset();
        $this->service()->cancel($this->request($asset, false));
        $this->reject($this->request($asset));

        $this->assertSame(DisposalStatus::PendingApproval, $this->request($asset)->status);
    }

    public function test_submit_hanya_dari_draft(): void
    {
        $request = $this->request($this->makeAsset());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mengajukan disposal');
        $this->service()->submit($request, $this->user('staff'));
    }

    public function test_submit_memeriksa_ulang_status_aset(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset, false);
        $this->setAssetStatus($asset, AssetStatus::Assigned);

        try {
            $this->service()->submit($request, $this->user('staff'));
            $this->fail('Submit untuk aset yang ditugaskan seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Ditugaskan', $e->getMessage());
        }
        $this->assertSame(DisposalStatus::Draft, $request->fresh()->status);
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
    }

    public function test_aset_pending_disposal_tidak_dapat_dipinjam_atau_diperbaiki(): void
    {
        $asset = $this->makeAsset();
        $this->request($asset);
        $staff = $this->user('staff');

        try {
            app(LoanService::class)->create(['asset_ids' => [$asset->id], 'purpose' => 'x', 'start_date' => today()->toDateString(), 'due_date' => today()->toDateString()], $staff, false);
            $this->fail('Aset PENDING_DISPOSAL seharusnya tidak dapat dipinjam');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Menunggu Disposal', $e->getMessage());
        }

        $this->expectException(BusinessRuleException::class);
        app(AssetService::class)->changeOperationalStatus($asset, 'repair', 'Coba perbaiki');
    }

    // ------------------------------------------------------------ approve / reject / cancel

    public function test_approve_memberi_tahu_admin_dan_aset_tetap_pending_disposal(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);

        $this->assertSame(DisposalStatus::Approved, $request->fresh()->status);
        $this->assertSame(AssetStatus::PendingDisposal, $asset->fresh()->status);
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Disposal siap dieksekusi');
    }

    public function test_reject_mengembalikan_status_aset_sebelumnya(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::InRepair]);
        $request = $this->request($asset);
        $this->reject($request, 'Masih bisa diperbaiki vendor');

        $this->assertSame(DisposalStatus::Rejected, $request->fresh()->status);
        $this->assertSame(AssetStatus::InRepair, $asset->fresh()->status);
        $event = $this->lastEvent($asset);
        $this->assertSame(AssetEventType::DisposalRejected, $event->event_type);
        $this->assertSame(AssetStatus::PendingDisposal, $event->from_status);
        $this->assertSame(AssetStatus::InRepair, $event->to_status);
        $this->assertSame('Ditolak: Masih bisa diperbaiki vendor', $event->notes);
    }

    public function test_cancel_pending_mengembalikan_status_aset(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::Lost]);
        $request = $this->request($asset);
        $this->service()->cancel($request);

        $request->refresh();
        $this->assertSame(DisposalStatus::Cancelled, $request->status);
        $this->assertNotNull($request->cancelled_at);
        $this->assertSame(ApprovalStatus::Cancelled, $request->approvalRequest->status);
        $this->assertSame(AssetStatus::Lost, $asset->fresh()->status);
        $this->assertSame(AssetEventType::DisposalCancelled, $this->lastEvent($asset)->event_type);
    }

    public function test_cancel_draft_tidak_membuat_event(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset, false);
        $before = AssetEvent::where('asset_id', $asset->id)->count();
        $this->service()->cancel($request);

        $this->assertSame(DisposalStatus::Cancelled, $request->fresh()->status);
        $this->assertSame($before, AssetEvent::where('asset_id', $asset->id)->count());
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_cancel_setelah_disetujui_tanpa_alasan_ditolak(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Alasan pembatalan wajib diisi');
        $this->service()->cancel($request->fresh(), $this->user('staff'), 'abc');
    }

    public function test_cancel_setelah_disetujui_dengan_alasan_oleh_petugas(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);
        $admin = $this->user('admin.aset');

        $this->service()->cancel($request->fresh(), $admin, 'Kebutuhan dibatalkan manajemen');

        $request->refresh();
        $this->assertSame(DisposalStatus::Cancelled, $request->status);
        $this->assertSame('Kebutuhan dibatalkan manajemen', $request->cancel_reason);
        $this->assertSame($admin->id, $request->cancelled_by_user_id);
        $this->assertNotNull($request->cancelled_at);
        $this->assertSame('APPROVED', $request->approvalRequest->status->value, 'Keputusan approval tetap tercatat');
        $this->assertDatabaseHas('audit_logs', ['action' => 'cancelled_after_approval', 'auditable_id' => $request->id]);
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan dibatalkan');
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status, 'Aset kembali dari Menunggu Disposal');
    }

    // ------------------------------------------------------------ execute

    public function test_execute_memerlukan_status_approved(): void
    {
        $request = $this->request($this->makeAsset());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mengeksekusi disposal');
        $this->execute($request);
    }

    public function test_execute_draft_ditolak(): void
    {
        $request = $this->request($this->makeAsset(), false);

        $this->expectException(BusinessRuleException::class);
        $this->execute($request);
    }

    public function test_execute_menjadikan_aset_disposed(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);
        $this->execute($request, ['attachments' => [$this->pdf('berita-acara.pdf')]]);

        $request->refresh();
        $this->assertSame(DisposalStatus::Completed, $request->status);
        $this->assertSame(DisposalMethod::Sale, $request->actual_method);
        $this->assertSame(today()->toDateString(), $request->executed_at->toDateString());
        $this->assertSame($this->user('admin.aset')->id, $request->executed_by_user_id);
        $this->assertEquals(750000, (float) $request->proceeds_amount);
        $this->assertSame('CV Barang Bekas', $request->disposal_recipient);
        $this->assertNotNull($request->completed_at);
        $this->assertSame(1, $request->attachments()->where('category', 'DISPOSAL_EVIDENCE')->count());

        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
        $event = $this->lastEvent($asset);
        $this->assertSame(AssetEventType::Disposed, $event->event_type);
        $this->assertSame(AssetStatus::PendingDisposal, $event->from_status);
        $this->assertSame('CV Barang Bekas', $event->metadata['recipient']);
        $this->assertEquals(750000, $event->metadata['proceeds_amount']);
    }

    public function test_execute_tanpa_penerima_dan_hasil_menyimpan_metadata_kosong(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);
        $this->execute($request, ['actual_method' => 'SCRAP', 'disposal_recipient' => null, 'proceeds_amount' => null]);

        $this->assertNull($request->fresh()->proceeds_amount);
        $this->assertEmpty($this->lastEvent($asset)->metadata ?? []);
    }

    public function test_execute_ditolak_bila_status_aset_bukan_pending_disposal(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);
        $this->setAssetStatus($asset, AssetStatus::Available);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak valid untuk disposal');
        $this->execute($request);
    }

    public function test_execute_dua_kali_ditolak(): void
    {
        $request = $this->request($this->makeAsset());
        $this->approve($request);
        $this->execute($request);

        $this->expectException(BusinessRuleException::class);
        $this->execute($request);
    }

    public function test_aset_disposed_bersifat_final(): void
    {
        $asset = $this->makeAsset();
        $request = $this->request($asset);
        $this->approve($request);
        $this->execute($request);

        try {
            $this->request($asset->fresh(), false);
            $this->fail('Aset DISPOSED tidak boleh diajukan disposal lagi');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Dihapus', $e->getMessage());
        }

        $this->expectException(QueryException::class);
        $this->setAssetStatus($asset, AssetStatus::Available);
    }
}
