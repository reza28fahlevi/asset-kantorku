<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\EmploymentStatus;
use App\Models\ApprovalStep;
use App\Models\Asset;
use App\Models\AssetLoanRequest;
use App\Models\DisposalRequest;
use App\Models\Employee;
use App\Models\ProcurementRequest;
use App\Models\Setting;
use App\Support\Like;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ActsAsDemoUsers;
use Tests\TestCase;

/**
 * Aturan yang diperketat: pembatalan setelah disetujui, approver nonaktif & pengalihan,
 * escape wildcard pencarian, akses berita acara, dan validasi transaksi tambahan.
 */
class StrictRulesTest extends TestCase
{
    use ActsAsDemoUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function submitProcurement(string $title = 'Pengadaan Uji'): ProcurementRequest
    {
        $this->assertSucceeded($this->actingAs($this->user('staff'))->post(route('procurements.store'), [
            'title' => $title, 'justification' => 'Kebutuhan tim', 'department_id' => 2, 'action' => 'submit',
            'items' => [['asset_category_id' => 8, 'item_name' => 'Meja', 'quantity' => 2, 'estimated_unit_price' => 1000000]],
        ]));

        return ProcurementRequest::latest('id')->firstOrFail();
    }

    // ---------------------------------------------------------------- pencarian

    public function test_like_escape_karakter_khusus(): void
    {
        $this->assertSame('100\%', Like::escape('100%'));
        $this->assertSame('a\_b', Like::escape('a_b'));
        $this->assertSame('c:\\\\x', Like::escape('c:\\x'));
        $this->assertSame('%\%%', Like::contains(' % '));
    }

    public function test_wildcard_di_pencarian_dicari_apa_adanya(): void
    {
        $pr = $this->submitProcurement('Laptop Tim Sales');
        $admin = $this->user('admin.aset');

        $this->actingAs($admin)->get(route('procurements.index', ['q' => 'Laptop']))->assertOk()->assertSee($pr->request_no);
        foreach (['%', '_', 'Lap%Sales', 'Lapto_'] as $term) {
            $this->actingAs($admin)->get(route('procurements.index', ['q' => $term]))->assertOk()->assertDontSee($pr->request_no);
        }
        foreach (['assignments', 'loans', 'disposals', 'masters.employees', 'masters.departments', 'masters.vendors', 'admin.users'] as $page) {
            $user = str_starts_with($page, 'admin') || str_starts_with($page, 'masters') ? $this->user('sysadmin') : $admin;
            $this->actingAs($user)->get(route("{$page}.index", ['q' => '%_%']))->assertOk();
        }
    }

    // ---------------------------------------------------------------- approver nonaktif

    public function test_approver_dengan_karyawan_nonaktif_bukan_approver(): void
    {
        $manager = $this->user('manager.it');
        $this->assertTrue($manager->isApprover());

        $manager->employee->update(['employment_status' => EmploymentStatus::Inactive]);
        $this->assertFalse($manager->fresh()->isApprover());
    }

    public function test_step_approver_nonaktif_dialihkan_ke_eskalasi_dan_approver_lama_ditolak(): void
    {
        Setting::put('approval.escalation_employee_id', '1'); // direktur
        $pr = $this->submitProcurement();
        $oldStep = $pr->approvalRequest->steps()->firstOrFail();
        $this->assertSame(4, $oldStep->approver_employee_id); // manager.it

        // Sysadmin menonaktifkan manager.it → step dialihkan otomatis
        $sysadmin = $this->user('sysadmin');
        $emp = Employee::findOrFail(4);
        $response = $this->actingAs($sysadmin)->put(route('masters.employees.update', $emp), [
            'employee_no' => $emp->employee_no, 'name' => $emp->name, 'email' => $emp->email, 'department_id' => $emp->department_id,
            'manager_employee_id' => $emp->manager_employee_id, 'job_title' => $emp->job_title, 'employment_status' => 'INACTIVE',
        ]);
        $this->assertSucceeded($response);
        $response->assertSessionHas('info');

        $this->assertSame('CANCELLED', $oldStep->fresh()->status->value);
        $newStep = ApprovalStep::where('approval_request_id', $pr->approval_request_id)->where('status', 'PENDING')->firstOrFail();
        $this->assertSame(1, $newStep->approver_employee_id);
        $this->assertSame(ApprovalStep::SOURCE_ESCALATION, $newStep->approver_source);
        $this->assertSame(2, $newStep->step_order);

        // Approver lama tidak dapat memutuskan lagi
        $this->actingAs($this->user('manager.it'))->post(route('approvals.approve', $oldStep), ['comment' => 'ok'])->assertForbidden();

        // Approver pengganti menyetujui → permintaan disetujui
        $this->assertSucceeded($this->actingAs($this->user('direktur'))->post(route('approvals.approve', $newStep), ['comment' => 'OK']));
        $this->assertSame('APPROVED', $pr->fresh()->status->value);
    }

    public function test_pengalihan_gagal_bila_eskalasi_tidak_valid_memberi_peringatan(): void
    {
        Setting::put('approval.escalation_employee_id', null);
        $pr = $this->submitProcurement();
        Employee::whereKey(4)->update(['employment_status' => 'INACTIVE']);

        $response = $this->actingAs($this->user('sysadmin'))->put(route('admin.settings.update'), [
            'app_company_name' => 'PT Uji', 'loan_max_duration_days' => 30, 'attachment_max_size_kb' => 5120,
        ]);
        $this->assertSucceeded($response);

        // Command terjadwal melaporkan kegagalan tanpa merusak step lama
        $this->artisan('approvals:reassign')->expectsOutputToContain('1 gagal')->assertSuccessful();
        $this->assertSame('PENDING', $pr->approvalRequest->steps()->firstOrFail()->status->value);
    }

    // ---------------------------------------------------------------- batal setelah disetujui

    public function test_batal_loan_disetujui_wajib_alasan_dan_bisa_oleh_petugas(): void
    {
        $asset = Asset::where('asset_tag', 'PRJ-2026-00001')->firstOrFail();
        $this->assertSucceeded($this->actingAs($this->user('staff'))->post(route('loans.store'), [
            'purpose' => 'Presentasi', 'start_date' => today()->toDateString(), 'due_date' => today()->addDays(2)->toDateString(),
            'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $loan = AssetLoanRequest::latest('id')->firstOrFail();
        $this->approveAll($loan->approvalRequest);
        $this->assertPageOk($this->user('admin.aset'), route('loans.show', $loan));

        $admin = $this->user('admin.aset');
        $this->actingAs($admin)->post(route('loans.cancel', $loan), ['cancel_reason' => ''])->assertSessionHas('error');
        $this->assertSame('APPROVED', $loan->fresh()->status->value);

        $this->assertSucceeded($this->actingAs($admin)->post(route('loans.cancel', $loan), ['cancel_reason' => 'Acara klien dibatalkan']));
        $loan->refresh();
        $this->assertSame('CANCELLED', $loan->status->value);
        $this->assertSame('Acara klien dibatalkan', $loan->cancel_reason);
        $this->actingAs($admin)->get(route('loans.show', $loan))->assertOk()->assertSee('Acara klien dibatalkan');

        // Auditor/manager tidak boleh membatalkan
        $this->actingAs($this->user('auditor'))->post(route('loans.cancel', $loan), ['cancel_reason' => 'xxxxx'])->assertForbidden();
    }

    public function test_batal_disposal_disetujui_mengembalikan_status_aset(): void
    {
        $admin = $this->user('admin.aset');
        $asset = Asset::where('asset_tag', 'NET-2026-00001')->firstOrFail();
        $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.store'), [
            'asset_id' => $asset->id, 'reason_type' => 'OBSOLETE', 'reason' => 'Sudah usang', 'planned_method' => 'SALE', 'action' => 'submit',
        ]));
        $disposal = DisposalRequest::latest('id')->firstOrFail();
        $this->approveAll($disposal->approvalRequest);
        $this->assertSame(AssetStatus::PendingDisposal, $asset->fresh()->status);

        $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.cancel', $disposal), ['cancel_reason' => 'Aset masih dipakai tim NOC']));
        $this->assertSame('CANCELLED', $disposal->fresh()->status->value);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);

        // Aset bisa diajukan disposal lagi (tidak terblokir)
        $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.store'), [
            'asset_id' => $asset->id, 'reason_type' => 'OBSOLETE', 'reason' => 'Usang', 'planned_method' => 'SALE', 'action' => 'draft',
        ]));
    }

    public function test_batal_assignment_disetujui_membuka_blokir_disposal(): void
    {
        $asset = Asset::where('asset_tag', 'LPT-2026-00002')->firstOrFail();
        $this->assertSucceeded($this->actingAs($this->user('staff'))->post(route('assignments.store'), [
            'location_id' => 3, 'purpose' => 'Laptop kerja', 'start_date' => today()->toDateString(), 'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $req = \App\Models\AssignmentRequest::latest('id')->firstOrFail();
        $this->approveAll($req->approvalRequest);

        $this->assertSucceeded($this->actingAs($this->user('staff'))->post(route('assignments.cancel', $req), ['cancel_reason' => 'Karyawan batal bergabung']));
        $this->assertSame('CANCELLED', $req->fresh()->status->value);

        $this->assertSucceeded($this->actingAs($this->user('admin.aset'))->post(route('disposals.store'), [
            'asset_id' => $asset->id, 'reason_type' => 'OBSOLETE', 'reason' => 'Usang', 'planned_method' => 'SALE', 'action' => 'submit',
        ]));
    }

    // ---------------------------------------------------------------- lampiran berita acara

    public function test_pemegang_bisa_mengunduh_berita_acara_serah_terima(): void
    {
        $asset = Asset::where('asset_tag', 'LPT-2026-00001')->firstOrFail();
        $staff = $this->user('staff');
        $this->assertSucceeded($this->actingAs($staff)->post(route('assignments.store'), [
            'location_id' => 3, 'purpose' => 'Laptop kerja', 'start_date' => today()->toDateString(), 'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $req = \App\Models\AssignmentRequest::latest('id')->firstOrFail();
        $this->approveAll($req->approvalRequest);
        $this->assertSucceeded($this->actingAs($this->user('admin.aset'))->post(route('assignments.handover', $req), [
            'assigned_at' => now()->subMinute()->format('Y-m-d H:i'), 'condition_out' => 'GOOD',
            'attachments' => [UploadedFile::fake()->create('bast.pdf', 20, 'application/pdf')],
        ]));

        $attachment = $asset->fresh()->activeAssignment->attachments()->firstOrFail();
        $this->actingAs($staff)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($this->user('manager.it'))->get(route('attachments.download', $attachment))->assertOk(); // approver permintaan
        $this->actingAs($this->user('sysadmin'))->get(route('attachments.download', $attachment))->assertForbidden();
    }

    // ---------------------------------------------------------------- validasi tambahan

    public function test_penerimaan_diterima_plus_ditolak_tidak_boleh_melebihi_sisa(): void
    {
        $pr = $this->submitProcurement();
        $this->approveAll($pr->approvalRequest);
        $admin = $this->user('admin.aset');
        $this->assertSucceeded($this->actingAs($admin)->post(route('procurements.order', $pr), [
            'vendor_id' => 1, 'po_number' => 'PO-STRICT', 'ordered_at' => now()->subHour()->format('Y-m-d H:i'),
        ]));
        $item = $pr->items()->firstOrFail(); // qty 2

        $this->actingAs($admin)->post(route('procurements.receive', $pr), [
            'received_at' => now()->subMinutes(5)->format('Y-m-d H:i'),
            'items' => [$item->id => ['accepted' => 1, 'rejected' => 2, 'location_id' => 2, 'condition' => 'GOOD']],
        ])->assertSessionHas('error');
        $this->assertSame(0, $item->fresh()->quantity_received);
    }

    public function test_submit_draft_peminjaman_dicek_ulang_durasi_maksimal(): void
    {
        $staff = $this->user('staff');
        $asset = Asset::where('asset_tag', 'PRJ-2026-00001')->firstOrFail();
        $this->assertSucceeded($this->actingAs($staff)->post(route('loans.store'), [
            'purpose' => 'Event', 'start_date' => today()->toDateString(), 'due_date' => today()->addDays(10)->toDateString(),
            'asset_ids' => [$asset->id], 'action' => 'draft',
        ]));
        $loan = AssetLoanRequest::latest('id')->firstOrFail();
        $this->assertSame('DRAFT', $loan->status->value);

        Setting::put('loan.max_duration_days', '5');
        $this->actingAs($staff)->post(route('loans.submit', $loan))->assertSessionHas('error');
        $this->assertSame('DRAFT', $loan->fresh()->status->value);
    }

    public function test_garansi_berakhir_hari_ini_dianggap_segera_berakhir(): void
    {
        $asset = new Asset(['warranty_end_date' => today()]);
        $this->assertTrue($asset->isWarrantyExpiringSoon());
        $this->assertFalse((new Asset(['warranty_end_date' => today()->subDay()]))->isWarrantyExpiringSoon());
        $this->assertFalse((new Asset(['warranty_end_date' => today()->addDays(31)]))->isWarrantyExpiringSoon());
    }
}
