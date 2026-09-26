<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ExtensionStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\Attachment;
use App\Models\LoanExtensionRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\ApprovalService;
use App\Services\AssignmentService;
use App\Services\LoanService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class LoanServiceTest extends ServiceTestCase
{
    private function service(): LoanService
    {
        return app(LoanService::class);
    }

    private function loanRequest(User $user, array $assetIds, array $overrides = [], bool $submit = true): AssetLoanRequest
    {
        $this->actingAs($user);

        return $this->service()->create(array_merge([
            'asset_ids' => $assetIds,
            'purpose' => 'Presentasi ke klien',
            'usage_location_id' => 2,
            'start_date' => today()->toDateString(),
            'due_date' => today()->addDays(3)->toDateString(),
        ], $overrides), $user, $submit)->fresh();
    }

    private function checkout(AssetLoanRequest $request, array $overrides = []): void
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $this->service()->checkout($request->fresh(), array_merge([
            'checked_out_at' => now()->subMinute()->format('Y-m-d H:i'),
            'condition_out' => AssetCondition::Good->value,
        ], $overrides), $admin);
    }

    /** Alur lengkap staff: ajukan → disetujui manager → diserahkan admin. */
    private function checkedOutLoan(?Asset $asset = null, array $overrides = []): AssetLoan
    {
        $asset ??= $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id], $overrides);
        $this->approve($request);
        $this->checkout($request);

        return AssetLoan::where('asset_loan_request_id', $request->id)->firstOrFail();
    }

    /** Loan aktif dengan tanggal lampau (dibuat langsung) untuk skenario jatuh tempo. */
    private function pastLoan(int $checkedOutDaysAgo, int $dueDaysAgo, int $borrowerId = 5): AssetLoan
    {
        $asset = $this->makeAsset(['status' => AssetStatus::OnLoan]);
        $due = now()->subDays($dueDaysAgo)->startOfSecond();

        return AssetLoan::create([
            'asset_id' => $asset->id,
            'borrower_employee_id' => $borrowerId,
            'checked_out_at' => now()->subDays($checkedOutDaysAgo)->startOfSecond(),
            'due_at' => $due,
            'original_due_at' => $due,
            'condition_out' => AssetCondition::Good,
            'status' => LoanStatus::CheckedOut,
        ])->fresh();
    }

    private function returnData(array $overrides = []): array
    {
        return array_merge([
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'condition_in' => AssetCondition::Good->value,
            'next_status' => AssetStatus::Available->value,
            'location_id' => 4,
        ], $overrides);
    }

    private function extend(AssetLoan $loan, string $date, ?User $user = null): LoanExtensionRequest
    {
        $user ??= $this->user('staff');
        $this->actingAs($user);

        return $this->service()->requestExtension($loan->fresh(), ['requested_due_date' => $date, 'reason' => 'Proyek diperpanjang'], $user)->fresh();
    }

    // ------------------------------------------------------------ create / submit

    public function test_create_draft_menyimpan_permintaan_tanpa_approval(): void
    {
        $asset = $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id], [], false);

        $this->assertSame(RequestStatus::Draft, $request->status);
        $this->assertMatchesRegularExpression('/^LN-'.now()->format('Y').'-\d{5}$/', $request->request_no);
        $this->assertSame(5, $request->requester_employee_id);
        $this->assertSame(5, $request->borrower_employee_id);
        $this->assertSame($this->user('staff')->id, $request->created_by_user_id);
        $this->assertNull($request->approval_request_id);
        $this->assertNull($request->submitted_at);
        $this->assertSame([$asset->id], $request->assets()->pluck('assets.id')->all());
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_create_dengan_submit_membuat_approval_ke_manager_peminjam(): void
    {
        $asset = $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id]);

        $this->assertSame(RequestStatus::PendingApproval, $request->status);
        $this->assertNotNull($request->submitted_at);
        $approval = $request->approvalRequest;
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $step = $approval->steps()->sole();
        $this->assertSame(4, $step->approver_employee_id);
        $this->assertSame(ApprovalStep::SOURCE_MANAGER, $step->approver_source);
        Notification::assertSentTo($this->user('manager.it'), AppNotification::class,
            fn (AppNotification $n) => $n->title === 'Permintaan approval baru' && str_contains($n->message, $request->request_no));
    }

    public function test_nomor_permintaan_berurutan(): void
    {
        $first = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], [], false);
        $second = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], [], false);

        $this->assertSame((int) Str::afterLast($first->request_no, '-') + 1, (int) Str::afterLast($second->request_no, '-'));
    }

    public function test_staff_tanpa_izin_create_for_others_tetap_meminjam_untuk_diri_sendiri(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['borrower_employee_id' => 7], false);

        $this->assertSame(5, $request->borrower_employee_id);
    }

    public function test_admin_dapat_meminjam_atas_nama_karyawan_lain_dan_approver_manager_peminjam(): void
    {
        $request = $this->loanRequest($this->user('admin.aset'), [$this->makeAsset()->id], ['borrower_employee_id' => 7]);

        $this->assertSame(7, $request->borrower_employee_id);
        $this->assertSame(3, $request->requester_employee_id);
        // Manager Dimas (emp 7) = Dewi (emp 4), bukan manager admin (emp 1)
        $this->assertSame(4, $request->approvalRequest->steps()->sole()->approver_employee_id);
    }

    public function test_peminjam_nonaktif_ditolak(): void
    {
        $this->setEmploymentStatus(7, EmploymentStatus::Inactive);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Peminjam');
        $this->loanRequest($this->user('admin.aset'), [$this->makeAsset()->id], ['borrower_employee_id' => 7]);
    }

    public function test_user_tanpa_karyawan_tidak_dapat_membuat_permintaan(): void
    {
        $user = $this->user('staff');
        $user->employee_id = null;

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('belum ditautkan');
        $this->service()->create(['asset_ids' => [$this->makeAsset()->id], 'purpose' => 'x', 'start_date' => today()->toDateString(), 'due_date' => today()->toDateString()], $user, false);
    }

    public function test_pemohon_cuti_tidak_dapat_membuat_permintaan(): void
    {
        $this->setEmploymentStatus(5, EmploymentStatus::OnLeave);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('nonaktif/cuti');
        $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], [], false);
    }

    public function test_durasi_tepat_batas_maksimal_diizinkan(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['due_date' => today()->addDays(30)->toDateString()], false);

        $this->assertSame(RequestStatus::Draft, $request->status);
    }

    public function test_durasi_melebihi_batas_default_ditolak(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('maksimal 30 hari');
        $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['due_date' => today()->addDays(31)->toDateString()], false);
    }

    public function test_durasi_maksimal_mengikuti_pengaturan(): void
    {
        Setting::put('loan.max_duration_days', 5);
        $ok = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['due_date' => today()->addDays(5)->toDateString()], false);
        $this->assertSame(RequestStatus::Draft, $ok->status);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('maksimal 5 hari');
        $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['due_date' => today()->addDays(6)->toDateString()], false);
    }

    public function test_pengaturan_durasi_nol_berarti_tanpa_batas(): void
    {
        Setting::put('loan.max_duration_days', 0);
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], ['due_date' => today()->addDays(400)->toDateString()], false);

        $this->assertSame(RequestStatus::Draft, $request->status);
    }

    public function test_aset_tidak_tersedia_ditolak_saat_create(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::InRepair]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage($asset->asset_tag);
        $this->loanRequest($this->user('staff'), [$asset->id], [], false);
    }

    public function test_aset_yang_sedang_ditugaskan_tidak_dapat_dipinjam(): void
    {
        $asset = $this->makeAsset();
        $staff = $this->user('staff');
        $this->actingAs($staff);
        $assignment = app(AssignmentService::class)->create([
            'asset_ids' => [$asset->id], 'location_id' => 3, 'purpose' => 'Kerja', 'start_date' => today()->toDateString(),
        ], $staff, true);
        $this->approve($assignment);
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        app(AssignmentService::class)->handover($assignment->fresh(), ['assigned_at' => now()->subMinute()->toDateTimeString(), 'condition_out' => 'GOOD'], $admin);
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Aset tidak tersedia');
        $this->loanRequest($staff, [$asset->id], [], false);
    }

    public function test_submit_hanya_dari_draft(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mengajukan peminjaman');
        $this->service()->submit($request, $this->user('staff'));
    }

    public function test_submit_ditolak_jika_tanggal_mulai_sudah_lewat(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], [], false);
        AssetLoanRequest::whereKey($request->id)->update(['start_date' => today()->subDay()->toDateString()]);

        try {
            $this->service()->submit($request, $this->user('staff'));
            $this->fail('Submit dengan tanggal mulai lampau seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Tanggal mulai sudah lewat', $e->getMessage());
        }
        $this->assertSame(RequestStatus::Draft, $request->fresh()->status);
        $this->assertNull($request->fresh()->approval_request_id);
    }

    public function test_submit_ditolak_jika_peminjam_menjadi_nonaktif(): void
    {
        $request = $this->loanRequest($this->user('admin.aset'), [$this->makeAsset()->id], ['borrower_employee_id' => 7], false);
        $this->setEmploymentStatus(7, EmploymentStatus::Inactive);

        $this->expectException(BusinessRuleException::class);
        $this->service()->submit($request, $this->user('admin.aset'));
    }

    public function test_submit_ditolak_bila_aset_tidak_lagi_tersedia(): void
    {
        $asset = $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id], [], false);
        $this->setAssetStatus($asset, AssetStatus::InRepair);

        $this->expectException(BusinessRuleException::class);
        $this->service()->submit($request, $this->user('staff'));
    }

    public function test_submit_ditolak_bila_tanggal_tumpang_tindih_dengan_peminjaman_lain(): void
    {
        $asset = $this->makeAsset();
        $first = $this->loanRequest($this->user('staff'), [$asset->id], ['due_date' => today()->addDays(5)->toDateString()]);

        try {
            $this->loanRequest($this->user('admin.aset'), [$asset->id], [
                'start_date' => today()->addDays(5)->toDateString(), 'due_date' => today()->addDays(7)->toDateString(),
            ]);
            $this->fail('Periode bertabrakan seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString("{$asset->asset_tag} → {$first->request_no}", $e->getMessage());
        }

        // Periode setelah due date permintaan pertama tidak bentrok
        $later = $this->loanRequest($this->user('admin.aset'), [$asset->id], [
            'start_date' => today()->addDays(6)->toDateString(), 'due_date' => today()->addDays(8)->toDateString(),
        ]);
        $this->assertSame(RequestStatus::PendingApproval, $later->status);
    }

    public function test_submit_ditolak_bila_aset_dipesan_assignment_terbuka(): void
    {
        $asset = $this->makeAsset();
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $assignment = app(AssignmentService::class)->create([
            'asset_ids' => [$asset->id], 'location_id' => 3, 'purpose' => 'Kerja', 'start_date' => today()->addDays(2)->toDateString(),
        ], $admin, true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage($assignment->request_no);
        $this->loanRequest($this->user('staff'), [$asset->id]);
    }

    public function test_permintaan_yang_dibatalkan_atau_ditolak_tidak_menghalangi(): void
    {
        $asset = $this->makeAsset();
        $cancelled = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->service()->cancel($cancelled);
        $rejected = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->reject($rejected);

        $again = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->assertSame(RequestStatus::PendingApproval, $again->status);
    }

    // ------------------------------------------------------------ cancel / approve / reject

    public function test_cancel_draft(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id], [], false);
        $this->service()->cancel($request);

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->cancelled_at);
    }

    public function test_cancel_pending_membatalkan_approval(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->service()->cancel($request);

        $request->refresh();
        $this->assertSame(RequestStatus::Cancelled, $request->status);
        $this->assertSame(ApprovalStatus::Cancelled, $request->approvalRequest->status);
        $this->assertNotNull($request->approvalRequest->closed_at);
        $this->assertSame(ApprovalStatus::Cancelled, $request->approvalRequest->steps()->sole()->status);
    }

    public function test_cancel_setelah_disetujui_tanpa_alasan_ditolak(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Alasan pembatalan wajib diisi');
        $this->service()->cancel($request->fresh(), $this->user('staff'), 'abc');
    }

    public function test_cancel_setelah_disetujui_dengan_alasan_oleh_petugas(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
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

    public function test_approve_mengubah_status_dan_memberi_tahu_admin(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $request->refresh();
        $this->assertSame(RequestStatus::Approved, $request->status);
        $this->assertSame(ApprovalStatus::Approved, $request->approvalRequest->status);
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class,
            fn (AppNotification $n) => $n->title === 'Peminjaman siap diserahterimakan');
        Notification::assertSentTo($this->user('staff'), AppNotification::class,
            fn (AppNotification $n) => $n->title === 'Permintaan disetujui');
    }

    public function test_reject_mengubah_status_tanpa_menyentuh_aset(): void
    {
        $asset = $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->reject($request, 'Aset dibutuhkan tim lain');

        $this->assertSame(RequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        Notification::assertSentTo($this->user('staff'), AppNotification::class,
            fn (AppNotification $n) => $n->title === 'Permintaan ditolak' && str_contains($n->message, 'Aset dibutuhkan tim lain'));
    }

    // ------------------------------------------------------------ checkout

    public function test_checkout_membuat_loan_per_aset_dan_aset_menjadi_on_loan(): void
    {
        $assets = [$this->makeAsset(), $this->makeAsset()];
        $request = $this->loanRequest($this->user('staff'), array_map(fn ($a) => $a->id, $assets));
        $this->approve($request);
        $this->checkout($request, ['condition_out' => 'FAIR', 'checkout_notes' => 'Lengkap dengan tas', 'attachments' => [$this->pdf()]]);

        $request->refresh();
        $this->assertSame(RequestStatus::Fulfilled, $request->status);
        $this->assertNotNull($request->fulfilled_at);
        $expectedDue = today()->addDays(3)->setTime(23, 59, 59)->toDateTimeString();

        foreach ($assets as $asset) {
            $loan = AssetLoan::where('asset_id', $asset->id)->sole();
            $this->assertSame(LoanStatus::CheckedOut, $loan->status);
            $this->assertSame($expectedDue, $loan->due_at->toDateTimeString());
            $this->assertSame($expectedDue, $loan->original_due_at->toDateTimeString());
            $this->assertSame(5, $loan->borrower_employee_id);
            $this->assertSame(2, $loan->usage_location_id);
            $this->assertSame(AssetCondition::Fair, $loan->condition_out);
            $this->assertSame('Lengkap dengan tas', $loan->checkout_notes);
            $this->assertSame($this->user('admin.aset')->id, $loan->checked_out_by_user_id);
            $this->assertFalse($loan->is_late);
            $this->assertSame(1, Attachment::where('attachable_type', $loan->getMorphClass())->where('attachable_id', $loan->id)->where('category', 'HANDOVER')->count());

            $asset->refresh();
            $this->assertSame(AssetStatus::OnLoan, $asset->status);
            $this->assertSame(AssetCondition::Fair, $asset->condition);
            $event = $this->lastEvent($asset);
            $this->assertSame(AssetEventType::LoanedOut, $event->event_type);
            $this->assertSame(AssetStatus::Available, $event->from_status);
            $this->assertSame(AssetStatus::OnLoan, $event->to_status);
            $this->assertSame('asset_loan', $event->reference_type);
            $this->assertSame($loan->id, $event->reference_id);
            $this->assertSame(5, $event->related_employee_id);
        }
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Aset pinjaman diserahkan');
    }

    public function test_checkout_selain_status_approved_ditolak(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('serah-terima peminjaman');
        $this->checkout($request);
    }

    public function test_checkout_di_masa_depan_ditolak(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('masa depan');
        $this->checkout($request, ['checked_out_at' => today()->addDays(4)->format('Y-m-d H:i')]);
    }

    public function test_checkout_sebelum_tanggal_mulai_ditolak(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sebelum tanggal mulai');
        $this->checkout($request, ['checked_out_at' => today()->subDay()->format('Y-m-d H:i')]);
    }

    public function test_checkout_tepat_pada_akhir_due_date_ditolak(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->checkout($request, ['checked_out_at' => today()->addDays(3)->setTime(23, 59, 59)->toDateTimeString()]);
    }

    public function test_checkout_gagal_bila_aset_tidak_lagi_tersedia_dan_tidak_ada_perubahan(): void
    {
        $asset = $this->makeAsset();
        $request = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->approve($request);
        $this->setAssetStatus($asset, AssetStatus::InRepair);

        try {
            $this->checkout($request);
            $this->fail('Checkout aset tidak tersedia seharusnya gagal');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Aset tidak tersedia', $e->getMessage());
        }
        $this->assertSame(RequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(0, AssetLoan::where('asset_id', $asset->id)->count());
    }

    public function test_checkout_ditolak_bila_peminjam_nonaktif(): void
    {
        $request = $this->loanRequest($this->user('staff'), [$this->makeAsset()->id]);
        $this->approve($request);
        $this->setEmploymentStatus(5, EmploymentStatus::Inactive);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Peminjam');
        $this->checkout($request);
    }

    // ------------------------------------------------------------ return

    public function test_pengembalian_tepat_waktu(): void
    {
        $loan = $this->checkedOutLoan();
        $admin = $this->user('admin.aset');
        $this->service()->returnLoan($loan, $this->returnData(['condition_in' => 'POOR', 'return_notes' => 'Baret halus']), $admin);

        $loan->refresh();
        $this->assertSame(LoanStatus::Returned, $loan->status);
        $this->assertFalse($loan->is_late);
        $this->assertNotNull($loan->returned_at);
        $this->assertSame($admin->id, $loan->returned_by_user_id);
        $this->assertSame(AssetCondition::Poor, $loan->condition_in);

        $asset = $loan->asset;
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertSame(4, $asset->location_id);
        $this->assertSame(AssetCondition::Poor, $asset->condition);
        $event = $this->lastEvent($asset);
        $this->assertSame(AssetEventType::LoanReturned, $event->event_type);
        $this->assertSame(AssetStatus::OnLoan, $event->from_status);
        $this->assertSame(4, $event->to_location_id);
        $this->assertFalse($event->metadata['late']);
        $this->assertSame('Baret halus', $event->notes);
        Notification::assertNotSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Pengembalian terlambat');
    }

    public function test_pengembalian_ke_perbaikan_tanpa_lokasi_mempertahankan_lokasi(): void
    {
        $asset = $this->makeAsset(['location_id' => 5]);
        $loan = $this->checkedOutLoan($asset);
        $this->service()->returnLoan($loan, $this->returnData(['next_status' => 'IN_REPAIR', 'location_id' => null, 'condition_in' => 'DAMAGED']), $this->user('admin.aset'));

        $asset->refresh();
        $this->assertSame(AssetStatus::InRepair, $asset->status);
        $this->assertSame(5, $asset->location_id);
        $this->assertSame(AssetCondition::Damaged, $asset->condition);
    }

    public function test_pengembalian_terlambat_ditandai_dan_dinotifikasi(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->actingAs($this->user('admin.aset'));
        $this->service()->returnLoan($loan, $this->returnData(['return_notes' => 'Maaf telat']), $this->user('admin.aset'));

        $loan->refresh();
        $this->assertTrue($loan->is_late);
        $this->assertSame(LoanStatus::Returned, $loan->status);
        $event = $this->lastEvent($loan->asset);
        $this->assertTrue($event->metadata['late']);
        $this->assertSame('TERLAMBAT. Maaf telat', $event->notes);
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Pengembalian terlambat');
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Pengembalian terlambat');
    }

    public function test_pengembalian_loan_overdue_tetap_ditandai_terlambat(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->service()->markOverdue();
        $this->assertSame(LoanStatus::Overdue, $loan->fresh()->status);

        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));

        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertTrue($loan->fresh()->is_late);
        $this->assertSame(AssetStatus::Available, $loan->asset->fresh()->status);
    }

    public function test_waktu_kembali_sebelum_serah_terima_ditolak(): void
    {
        $loan = $this->pastLoan(5, -3);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sebelum waktu serah-terima');
        $this->service()->returnLoan($loan, $this->returnData(['returned_at' => now()->subDays(6)->toDateTimeString()]), $this->user('admin.aset'));
    }

    public function test_loan_yang_sudah_dikembalikan_tidak_dapat_dikembalikan_lagi(): void
    {
        $loan = $this->checkedOutLoan();
        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('sudah ditutup');
        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));
    }

    public function test_pengembalian_membatalkan_perpanjangan_yang_menunggu(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());
        $step = $this->pendingStep($extension);

        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));

        $extension->refresh();
        $this->assertSame(ExtensionStatus::Cancelled, $extension->status);
        $this->assertNotNull($extension->cancelled_at);
        $this->assertSame(ApprovalStatus::Cancelled, $extension->approvalRequest->status);

        // Approval yang telah dibatalkan tidak dapat diputuskan lagi
        $manager = $this->user('manager.it');
        $this->expectException(BusinessRuleException::class);
        app(ApprovalService::class)->decide($step, $manager, true, 'OK');
    }

    // ------------------------------------------------------------ overdue

    public function test_mark_overdue_menandai_loan_lewat_jatuh_tempo(): void
    {
        $baseline = AssetLoan::where('status', 'CHECKED_OUT')->where('due_at', '<', now())->count();
        $late = $this->pastLoan(10, 1);
        $late2 = $this->pastLoan(10, 3, 7);
        $onTime = $this->pastLoan(2, -5);

        $this->assertSame($baseline + 2, $this->service()->markOverdue());

        foreach ([$late, $late2] as $loan) {
            $loan->refresh();
            $this->assertSame(LoanStatus::Overdue, $loan->status);
            $this->assertTrue($loan->is_late);
            $event = $this->lastEvent($loan->asset);
            $this->assertSame(AssetEventType::LoanOverdue, $event->event_type);
            $this->assertSame($loan->id, $event->reference_id);
            // Status aset tetap ON_LOAN
            $this->assertSame(AssetStatus::OnLoan, $loan->asset->status);
        }
        $this->assertSame(LoanStatus::CheckedOut, $onTime->fresh()->status);
        $this->assertFalse($onTime->fresh()->is_late);

        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Peminjaman terlambat');
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Peminjaman terlambat');

        // Idempoten: loan yang sudah OVERDUE tidak diproses ulang
        $this->assertSame($baseline, $this->service()->markOverdue());
        $this->assertSame(1, $late->asset->events()->where('event_type', 'LOAN_OVERDUE')->count());
    }

    public function test_mark_overdue_tidak_menyentuh_loan_yang_sudah_dikembalikan(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));
        $this->service()->markOverdue();

        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
    }

    /** Simulasikan transaksi lain yang mengubah loan tepat setelah markOverdue membaca daftar kandidat. */
    private function onFirstRetrieve(AssetLoan $target, array $changes): void
    {
        $done = false;
        AssetLoan::retrieved(function (AssetLoan $loan) use ($target, $changes, &$done) {
            if (! $done && $loan->id === $target->id) {
                $done = true;
                AssetLoan::whereKey($loan->id)->toBase()->update($changes);
            }
        });
    }

    public function test_mark_overdue_melewati_loan_yang_diperpanjang_setelah_dibaca(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->onFirstRetrieve($loan, ['due_at' => now()->addDays(5)->startOfSecond()->toDateTimeString()]);

        $this->service()->markOverdue();

        $this->assertSame(LoanStatus::CheckedOut, $loan->fresh()->status);
        $this->assertFalse($loan->fresh()->is_late);
        $this->assertSame(0, $loan->asset->events()->where('event_type', 'LOAN_OVERDUE')->count());
    }

    public function test_mark_overdue_melewati_loan_yang_dikembalikan_setelah_dibaca(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->onFirstRetrieve($loan, ['status' => 'RETURNED', 'returned_at' => now()->subHour()->toDateTimeString()]);

        $this->service()->markOverdue();

        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
        $this->assertSame(0, $loan->asset->events()->where('event_type', 'LOAN_OVERDUE')->count());
    }

    // ------------------------------------------------------------ extension

    public function test_request_extension_membuat_permintaan_ke_manager_peminjam(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());

        $this->assertSame(ExtensionStatus::PendingApproval, $extension->status);
        $this->assertMatchesRegularExpression('/^EXT-\d{4}-\d{5}$/', $extension->request_no);
        $this->assertSame($loan->due_at->toDateTimeString(), $extension->current_due_at->toDateTimeString());
        $this->assertSame(today()->addDays(10)->setTime(23, 59, 59)->toDateTimeString(), $extension->requested_due_at->toDateTimeString());
        $this->assertSame(5, $extension->requester_employee_id);
        $this->assertSame(4, $extension->approvalRequest->steps()->sole()->approver_employee_id);
        // Due date belum berubah sebelum disetujui
        $this->assertSame($loan->due_at->toDateTimeString(), $loan->fresh()->due_at->toDateTimeString());
    }

    public function test_request_extension_ditolak_bila_masih_ada_yang_menunggu(): void
    {
        $loan = $this->checkedOutLoan();
        $this->extend($loan, today()->addDays(10)->toDateString());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('menunggu approval');
        $this->extend($loan, today()->addDays(12)->toDateString());
    }

    public function test_request_extension_diizinkan_lagi_setelah_ditolak(): void
    {
        $loan = $this->checkedOutLoan();
        $first = $this->extend($loan, today()->addDays(10)->toDateString());
        $this->reject($first);

        $this->assertSame(ExtensionStatus::Rejected, $first->fresh()->status);
        $this->assertSame($loan->due_at->toDateTimeString(), $loan->fresh()->due_at->toDateTimeString());

        $second = $this->extend($loan, today()->addDays(8)->toDateString());
        $this->assertSame(ExtensionStatus::PendingApproval, $second->status);
    }

    public function test_request_extension_tanggal_sama_dengan_due_saat_ini_ditolak(): void
    {
        $loan = $this->checkedOutLoan();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('harus setelah due date saat ini');
        $this->extend($loan, $loan->due_at->toDateString());
    }

    public function test_request_extension_tanggal_sebelum_due_ditolak(): void
    {
        $loan = $this->checkedOutLoan();

        $this->expectException(BusinessRuleException::class);
        $this->extend($loan, today()->addDay()->toDateString());
    }

    public function test_request_extension_melebihi_durasi_maksimal_dari_serah_terima_ditolak(): void
    {
        $loan = $this->checkedOutLoan();
        $ok = $this->extend($loan, today()->addDays(30)->toDateString());
        $this->service()->cancelExtension($ok);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('maksimal 30 hari');
        $this->extend($loan, today()->addDays(31)->toDateString());
    }

    public function test_request_extension_oleh_bukan_peminjam_ditolak(): void
    {
        $loan = $this->checkedOutLoan();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hanya peminjam atau administrator');
        $this->extend($loan, today()->addDays(10)->toDateString(), $this->user('manager.it'));
    }

    public function test_admin_dapat_mengajukan_perpanjangan_untuk_peminjam(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString(), $this->user('admin.aset'));

        $this->assertSame(3, $extension->requester_employee_id);
        // Approver tetap manager peminjam
        $this->assertSame(4, $extension->approvalRequest->steps()->sole()->approver_employee_id);
    }

    public function test_request_extension_loan_yang_sudah_ditutup_ditolak(): void
    {
        $loan = $this->checkedOutLoan();
        $this->service()->returnLoan($loan, $this->returnData(), $this->user('admin.aset'));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hanya peminjaman aktif');
        $this->extend($loan, today()->addDays(10)->toDateString());
    }

    public function test_request_extension_bentrok_dengan_permintaan_berikutnya_ditolak(): void
    {
        $asset = $this->makeAsset();
        $first = $this->loanRequest($this->user('staff'), [$asset->id]);
        $this->approve($first);
        // Permintaan lain pada aset yang sama sesudah due date pertama (+5 s.d. +8)
        $next = $this->loanRequest($this->user('admin.aset'), [$asset->id], [
            'start_date' => today()->addDays(5)->toDateString(), 'due_date' => today()->addDays(8)->toDateString(),
        ]);
        $this->checkout($first);
        $loan = AssetLoan::where('asset_loan_request_id', $first->id)->sole();

        // Perpanjangan s.d. +4 tidak bentrok
        $ok = $this->extend($loan, today()->addDays(4)->toDateString());
        $this->service()->cancelExtension($ok);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage($next->request_no);
        $this->extend($loan, today()->addDays(6)->toDateString());
    }

    public function test_approve_extension_memperbarui_due_date_dan_mencatat_histori(): void
    {
        $loan = $this->checkedOutLoan();
        $originalDue = $loan->due_at->toDateTimeString();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());
        $this->approve($extension);

        $loan->refresh();
        $extension->refresh();
        $newDue = today()->addDays(10)->setTime(23, 59, 59)->toDateTimeString();
        $this->assertSame($newDue, $loan->due_at->toDateTimeString());
        $this->assertSame($originalDue, $loan->original_due_at->toDateTimeString());
        $this->assertSame(LoanStatus::CheckedOut, $loan->status);
        $this->assertSame(ExtensionStatus::Approved, $extension->status);
        $this->assertNotNull($extension->applied_at);
        $this->assertSame($originalDue, $extension->current_due_at->toDateTimeString());

        $event = $this->lastEvent($loan->asset);
        $this->assertSame(AssetEventType::LoanExtended, $event->event_type);
        $this->assertSame('loan_extension_request', $event->reference_type);
        $this->assertSame($extension->id, $event->reference_id);
        $this->assertSame($originalDue, $event->metadata['previous_due_at']);
        $this->assertSame($newDue, $event->metadata['new_due_at']);
        // Status aset tidak berubah
        $this->assertSame(AssetStatus::OnLoan, $loan->asset->status);
    }

    public function test_approve_extension_loan_overdue_kembali_checked_out(): void
    {
        $loan = $this->pastLoan(10, 2);
        $this->service()->markOverdue();
        $extension = $this->extend($loan, today()->addDays(3)->toDateString());
        $this->approve($extension);

        $loan->refresh();
        $this->assertSame(LoanStatus::CheckedOut, $loan->status);
        $this->assertTrue($loan->due_at->isFuture());
    }

    public function test_approve_extension_memeriksa_ulang_bentrok_jadwal(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());

        // Permintaan lain yang disetujui muncul setelah perpanjangan diajukan
        $other = AssetLoanRequest::create([
            'request_no' => 'UT2-LN-'.Str::upper(Str::random(6)),
            'requester_employee_id' => 7, 'created_by_user_id' => $this->user('admin.aset')->id, 'borrower_employee_id' => 7,
            'purpose' => 'Bentrok', 'start_date' => today()->addDays(6)->toDateString(), 'due_date' => today()->addDays(7)->toDateString(),
            'status' => RequestStatus::Approved,
        ]);
        $other->assets()->attach($loan->asset_id);

        try {
            $this->approve($extension);
            $this->fail('Perpanjangan yang bentrok seharusnya ditolak saat approve');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString($other->request_no, $e->getMessage());
        }
        $this->assertSame(ExtensionStatus::PendingApproval, $extension->fresh()->status);
        $this->assertSame($loan->due_at->toDateTimeString(), $loan->fresh()->due_at->toDateTimeString());
    }

    public function test_cancel_extension(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());
        $this->service()->cancelExtension($extension);

        $extension->refresh();
        $this->assertSame(ExtensionStatus::Cancelled, $extension->status);
        $this->assertNotNull($extension->cancelled_at);
        $this->assertSame(ApprovalStatus::Cancelled, $extension->approvalRequest->status);
        $this->assertSame($loan->due_at->toDateTimeString(), $loan->fresh()->due_at->toDateTimeString());

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('membatalkan perpanjangan');
        $this->service()->cancelExtension($extension);
    }

    public function test_cancel_extension_yang_sudah_disetujui_ditolak(): void
    {
        $loan = $this->checkedOutLoan();
        $extension = $this->extend($loan, today()->addDays(10)->toDateString());
        $this->approve($extension);

        $this->expectException(BusinessRuleException::class);
        $this->service()->cancelExtension($extension);
    }
}
