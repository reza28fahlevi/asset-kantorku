<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\AssetLoanRequest;
use App\Models\AssignmentRequest;
use App\Models\DisposalRequest;
use App\Models\ProcurementRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ActsAsDemoUsers;
use Tests\TestCase;

/**
 * Functional test end-to-end: aset (CRUD + status), pengadaan, serah terima, peminjaman,
 * disposal, approval inbox, export, dan halaman terkait (list/detail) di setiap tahap.
 */
class TransactionFlowTest extends TestCase
{
    use ActsAsDemoUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'dokumen.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 50, 'application/pdf');
    }

    public function test_asset_crud_status_and_export(): void
    {
        $admin = $this->user('admin.aset');

        $this->assertSucceeded($this->actingAs($admin)->post(route('assets.store'), [
            'name' => 'Laptop Uji', 'asset_category_id' => 1, 'location_id' => 2, 'condition' => 'GOOD',
            'brand' => 'Dell', 'model' => 'Latitude', 'serial_number' => 'SN-UJI-001',
            'purchase_date' => '2026-01-10', 'purchase_cost' => 15000000, 'warranty_end_date' => '2028-01-10',
        ]));
        $asset = Asset::where('serial_number', 'SN-UJI-001')->firstOrFail();
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertPageOk($admin, route('assets.show', $asset));
        $this->assertPageOk($admin, route('assets.edit', $asset));
        $this->assertPageOk($admin, route('assets.index', ['q' => 'Laptop Uji', 'status' => 'AVAILABLE', 'category_id' => 1, 'location_id' => 2]));

        $this->assertSucceeded($this->actingAs($admin)->put(route('assets.update', $asset), [
            'name' => 'Laptop Uji Ubah', 'location_id' => 2, 'serial_number' => 'SN-UJI-001', 'purchase_cost' => 14000000,
        ]));
        $this->assertSame('Laptop Uji Ubah', $asset->fresh()->name);

        foreach ([['repair', AssetStatus::InRepair], ['repaired', AssetStatus::Available], ['lost', AssetStatus::Lost], ['found', AssetStatus::Available]] as [$action, $expected]) {
            $this->assertSucceeded($this->actingAs($admin)->post(route('assets.status', $asset), ['action' => $action, 'notes' => "Uji {$action}", 'condition' => 'GOOD']));
            $this->assertSame($expected, $asset->fresh()->status, $action);
        }
        $this->assertPageOk($admin, route('assets.show', $asset));

        $this->actingAs($admin)->get(route('assets.export', ['format' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $xlsx = $this->actingAs($admin)->get(route('assets.export', ['format' => 'xlsx', 'q' => 'Laptop']));
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
        $pdf = $this->actingAs($admin)->get(route('assets.export', ['format' => 'pdf', 'status' => 'AVAILABLE']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($this->user('staff'))->get(route('assets.export', ['format' => 'pdf']))->assertForbidden();
    }

    public function test_procurement_draft_edit_submit_approve_order_receive(): void
    {
        $staff = $this->user('staff');       // requester (atasan: manager.it)
        $admin = $this->user('admin.aset');  // order & receive

        // Draft belum lengkap cukup judul; baris item tak lengkap diabaikan
        $this->assertSucceeded($this->actingAs($staff)->post(route('procurements.store'), [
            'title' => 'Draft Laptop', 'action' => 'draft', 'items' => [['item_name' => '', 'quantity' => 1]],
        ]));
        $pr = ProcurementRequest::where('title', 'Draft Laptop')->latest('id')->firstOrFail();
        $this->assertSame(0, $pr->items()->count());
        $this->assertPageOk($staff, route('procurements.show', $pr));
        $this->assertPageOk($staff, route('procurements.edit', $pr));

        // Submit draft kosong ditolak
        $this->actingAs($staff)->post(route('procurements.submit', $pr))->assertSessionHas('error');

        // Lengkapi draft lalu ajukan
        $this->assertSucceeded($this->actingAs($staff)->put(route('procurements.update', $pr), [
            'title' => 'Pengadaan Laptop Tim', 'justification' => 'Kebutuhan karyawan baru', 'department_id' => 2,
            'needed_by' => now()->addWeek()->toDateString(), 'action' => 'submit',
            'items' => [
                ['asset_category_id' => 1, 'item_name' => 'Laptop 14"', 'quantity' => 2, 'estimated_unit_price' => 12000000],
                ['asset_category_id' => 8, 'item_name' => 'Kursi Kerja', 'quantity' => 1, 'estimated_unit_price' => 1500000],
            ],
            'attachments' => [$this->pdf('quotation.pdf')],
        ]));
        $pr->refresh();
        $this->assertSame('PENDING_APPROVAL', $pr->status->value);
        $this->assertEquals(25500000, (float) $pr->estimated_total);
        $this->assertPageOk($this->user('manager.it'), route('approvals.index'));

        $this->approveAll($pr->approvalRequest);
        $this->assertSame('APPROVED', $pr->fresh()->status->value);

        $this->assertSucceeded($this->actingAs($admin)->post(route('procurements.order', $pr), [
            'vendor_id' => 1, 'po_number' => 'PO-UJI-001', 'ordered_at' => now()->subHour()->format('Y-m-d H:i'),
        ]));
        $this->assertSame('ORDERED', $pr->fresh()->status->value);
        $this->assertPageOk($admin, route('procurements.receive-form', $pr));

        [$laptop, $chair] = $pr->items()->orderBy('id')->get()->all();
        // Terima sebagian: 1 laptop; kursi 0 (lokasi tidak wajib)
        $this->assertSucceeded($this->actingAs($admin)->post(route('procurements.receive', $pr), [
            'received_at' => now()->subMinutes(30)->format('Y-m-d H:i'), 'delivery_note_no' => 'SJ-01',
            'items' => [
                $laptop->id => ['accepted' => 1, 'rejected' => 0, 'location_id' => 2, 'unit_cost' => 11900000, 'condition' => 'GOOD', 'serial_numbers' => 'SN-PR-001'],
                $chair->id => ['accepted' => 0, 'rejected' => 0, 'location_id' => ''],
            ],
        ]));
        $this->assertSame('PARTIALLY_RECEIVED', $pr->fresh()->status->value);
        $this->assertSame(1, Asset::where('serial_number', 'SN-PR-001')->count());

        // Lokasi wajib bila diterima > 0
        $this->actingAs($admin)->post(route('procurements.receive', $pr), [
            'received_at' => now()->subMinutes(10)->format('Y-m-d H:i'),
            'items' => [$chair->id => ['accepted' => 1, 'location_id' => '']],
        ])->assertSessionHasErrors("items.{$chair->id}.location_id");

        $this->assertSucceeded($this->actingAs($admin)->post(route('procurements.receive', $pr), [
            'received_at' => now()->subMinutes(5)->format('Y-m-d H:i'),
            'items' => [
                $laptop->id => ['accepted' => 1, 'location_id' => 2, 'condition' => 'GOOD', 'serial_numbers' => 'SN-PR-002'],
                $chair->id => ['accepted' => 1, 'location_id' => 2, 'condition' => 'GOOD'],
            ],
        ]));
        $this->assertSame('RECEIVED', $pr->fresh()->status->value);
        $this->assertPageOk($admin, route('procurements.show', $pr));
        $this->assertPageOk($admin, route('procurements.index', ['status' => 'RECEIVED']));
        $this->assertPageOk($admin, route('dashboard.widget', 'activity'));
    }

    public function test_procurement_reject_and_cancel(): void
    {
        $staff = $this->user('staff');
        $payload = [
            'title' => 'Monitor', 'justification' => 'Tambahan layar', 'department_id' => 2, 'action' => 'submit',
            'items' => [['asset_category_id' => 3, 'item_name' => 'Monitor 24"', 'quantity' => 1, 'estimated_unit_price' => 2500000]],
        ];
        $this->assertSucceeded($this->actingAs($staff)->post(route('procurements.store'), $payload));
        $pr = ProcurementRequest::latest('id')->firstOrFail();
        $step = $pr->approvalRequest->steps()->firstOrFail();
        $manager = $this->user('manager.it');

        $this->actingAs($manager)->post(route('approvals.reject', $step), ['comment' => 'no', 'step_id' => $step->id])->assertSessionHasErrors('comment');
        $this->assertSucceeded($this->actingAs($manager)->post(route('approvals.reject', $step), ['comment' => 'Anggaran belum tersedia']));
        $this->assertSame('REJECTED', $pr->fresh()->status->value);
        $this->assertPageOk($manager, route('approvals.index', ['tab' => 'history']));

        $this->assertSucceeded($this->actingAs($staff)->post(route('procurements.store'), $payload));
        $pr2 = ProcurementRequest::latest('id')->firstOrFail();
        $this->assertSucceeded($this->actingAs($staff)->post(route('procurements.cancel', $pr2)));
        $this->assertSame('CANCELLED', $pr2->fresh()->status->value);
    }

    public function test_assignment_request_approve_handover_return(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin.aset');
        $asset = Asset::where('asset_tag', 'LPT-2026-00001')->firstOrFail();

        $this->assertSucceeded($this->actingAs($staff)->post(route('assignments.store'), [
            'location_id' => 3, 'purpose' => 'Laptop kerja harian', 'start_date' => today()->toDateString(),
            'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $req = AssignmentRequest::latest('id')->firstOrFail();
        if ($req->status->value === 'DRAFT') {
            $this->assertSucceeded($this->actingAs($staff)->post(route('assignments.submit', $req)));
        }
        $this->assertPageOk($staff, route('assignments.show', $req));
        $this->approveAll($req->fresh()->approvalRequest);
        $this->assertSame('APPROVED', $req->fresh()->status->value);

        $this->assertSucceeded($this->actingAs($admin)->post(route('assignments.handover', $req), [
            'assigned_at' => now()->subMinutes(5)->format('Y-m-d H:i'), 'condition_out' => 'GOOD',
            'handover_document_no' => 'BAST-01', 'attachments' => [$this->pdf('bast.pdf')],
        ]));
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);
        $this->assertPageOk($admin, route('assignments.active'));
        $this->assertPageOk($staff, route('dashboard.widget', 'my-assets'));

        $assignment = $asset->fresh()->activeAssignment;
        $this->assertNotNull($assignment);
        $this->assertSucceeded($this->actingAs($admin)->post(route('assignments.return', $assignment), [
            'returned_at' => now()->format('Y-m-d H:i'), 'condition_in' => 'GOOD', 'next_status' => 'AVAILABLE', 'location_id' => 2,
        ]));
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertPageOk($admin, route('assignments.show', $req));
        $this->assertPageOk($admin, route('assignments.index', ['q' => $asset->asset_tag]));
    }

    public function test_loan_request_checkout_extend_return(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin.aset');
        $asset = Asset::where('asset_tag', 'PRJ-2026-00001')->firstOrFail();

        $this->assertSucceeded($this->actingAs($staff)->post(route('loans.store'), [
            'usage_location_id' => 4, 'purpose' => 'Presentasi klien', 'start_date' => today()->toDateString(),
            'due_date' => today()->addDays(3)->toDateString(), 'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $req = AssetLoanRequest::latest('id')->firstOrFail();
        if ($req->status->value === 'DRAFT') {
            $this->assertSucceeded($this->actingAs($staff)->post(route('loans.submit', $req)));
        }
        $this->approveAll($req->fresh()->approvalRequest);
        $this->assertSame('APPROVED', $req->fresh()->status->value);

        $this->assertSucceeded($this->actingAs($admin)->post(route('loans.checkout', $req), [
            'checked_out_at' => now()->subMinutes(5)->format('Y-m-d H:i'), 'condition_out' => 'GOOD',
        ]));
        $this->assertSame(AssetStatus::OnLoan, $asset->fresh()->status);
        $loan = AssetLoan::where('asset_id', $asset->id)->latest('id')->firstOrFail();
        $this->assertPageOk($admin, route('loans.active'));
        $this->assertPageOk($staff, route('loans.show', $req));

        // Perpanjangan: ajukan, cegah pengajuan ganda, batalkan, ajukan lagi lalu setujui
        $this->assertSucceeded($this->actingAs($staff)->post(route('loans.extend', $loan), [
            'requested_due_date' => today()->addDays(7)->toDateString(), 'reason' => 'Proyek diperpanjang',
        ]));
        $this->actingAs($staff)->post(route('loans.extend', $loan), [
            'requested_due_date' => today()->addDays(8)->toDateString(), 'reason' => 'Pengajuan kedua',
        ])->assertSessionHas('error');
        $ext = $loan->extensions()->latest('id')->firstOrFail();
        $this->assertSucceeded($this->actingAs($staff)->post(route('loans.extensions.cancel', $ext)));

        $this->assertSucceeded($this->actingAs($staff)->post(route('loans.extend', $loan), [
            'requested_due_date' => today()->addDays(7)->toDateString(), 'reason' => 'Proyek diperpanjang',
        ]));
        $this->approveAll($loan->extensions()->latest('id')->firstOrFail()->approvalRequest);
        $this->assertSame(today()->addDays(7)->toDateString(), $loan->fresh()->due_at->toDateString());
        $this->assertPageOk($staff, route('loans.show', $req));

        $this->assertSucceeded($this->actingAs($admin)->post(route('loans.return', $loan), [
            'returned_at' => now()->format('Y-m-d H:i'), 'condition_in' => 'FAIR', 'next_status' => 'AVAILABLE',
        ]));
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertPageOk($admin, route('loans.index', ['status' => $req->fresh()->status->value]));
    }

    public function test_disposal_request_approve_execute(): void
    {
        $admin = $this->user('admin.aset'); // requester (atasan: direktur)
        $asset = Asset::where('asset_tag', 'MON-2026-00001')->firstOrFail();
        $this->assertPageOk($admin, route('disposals.create', ['asset_id' => $asset->id]));

        $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.store'), [
            'asset_id' => $asset->id, 'reason_type' => 'DAMAGED', 'reason' => 'Panel rusak total',
            'planned_method' => 'SCRAP', 'action' => 'submit', 'attachments' => [$this->pdf('foto-kerusakan.pdf')],
        ]));
        $req = DisposalRequest::latest('id')->firstOrFail();
        if ($req->status->value === 'DRAFT') {
            $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.submit', $req)));
        }
        $this->assertSame(AssetStatus::PendingDisposal, $asset->fresh()->status);
        $this->assertPageOk($admin, route('disposals.show', $req));

        $this->approveAll($req->fresh()->approvalRequest);
        $this->assertSame('APPROVED', $req->fresh()->status->value);

        // Eksekusi wajib lampiran berita acara
        $this->actingAs($admin)->post(route('disposals.execute', $req), [
            'executed_at' => today()->toDateString(), 'actual_method' => 'SCRAP',
        ])->assertSessionHasErrors('attachments');

        $this->assertSucceeded($this->actingAs($admin)->post(route('disposals.execute', $req), [
            'executed_at' => today()->toDateString(), 'actual_method' => 'SALE', 'disposal_recipient' => 'PT Daur Ulang',
            'proceeds_amount' => 250000, 'attachments' => [$this->pdf('berita-acara.pdf')],
        ]));
        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
        $this->assertPageOk($admin, route('disposals.show', $req));
        $this->assertPageOk($admin, route('disposals.index', ['status' => 'COMPLETED']));
        $this->actingAs($admin)->get(route('assets.edit', $asset))->assertForbidden();
    }
}
