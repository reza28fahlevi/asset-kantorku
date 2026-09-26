<?php

namespace Tests\Unit\Services;

use App\Enums\ApprovalStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\ProcurementService;
use Illuminate\Support\Facades\Notification;

class ProcurementServiceTest extends ServiceTestCase
{
    private function service(): ProcurementService
    {
        return app(ProcurementService::class);
    }

    private function items(): array
    {
        return [
            ['asset_category_id' => 1, 'item_name' => 'Laptop 14"', 'specification' => 'i7/16GB', 'quantity' => 2, 'estimated_unit_price' => 12000000],
            ['asset_category_id' => 8, 'item_name' => 'Kursi Kerja', 'quantity' => 3, 'estimated_unit_price' => 1500000],
        ];
    }

    private function create(array $overrides = [], bool $submit = false, ?User $user = null): ProcurementRequest
    {
        $user ??= $this->user('staff');
        $this->actingAs($user);

        return $this->service()->create(array_merge([
            'title' => 'Pengadaan Tim IT',
            'justification' => 'Karyawan baru bergabung',
            'needed_by' => today()->addWeeks(2)->toDateString(),
            'items' => $this->items(),
        ], $overrides), $user, $submit)->fresh();
    }

    /** Procurement siap diterima (ORDERED). */
    private function ordered(array $items = []): ProcurementRequest
    {
        $request = $this->create($items ? ['items' => $items] : [], true);
        $this->approve($request);
        $this->actingAs($this->user('admin.aset'));
        $this->service()->order($request->fresh(), ['vendor_id' => 1, 'po_number' => 'PO-UT-001']);

        return $request->fresh();
    }

    private function receive(ProcurementRequest $request, array $lines, array $overrides = []): ProcurementReceipt
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);

        return $this->service()->receive($request->fresh(), array_merge([
            'received_at' => now()->subHour()->toDateTimeString(),
            'delivery_note_no' => 'SJ-UT-01',
            'items' => $lines,
        ], $overrides), $admin);
    }

    /** [laptopItem, kursiItem] */
    private function itemsOf(ProcurementRequest $request): array
    {
        return $request->items()->orderBy('id')->get()->all();
    }

    // ------------------------------------------------------------ create / update

    public function test_create_draft_menghitung_total_dan_departemen_default(): void
    {
        $request = $this->create();

        $this->assertSame(ProcurementStatus::Draft, $request->status);
        $this->assertMatchesRegularExpression('/^PR-\d{4}-\d{5}$/', $request->request_no);
        $this->assertSame(5, $request->requester_employee_id);
        $this->assertSame(2, $request->department_id);
        $this->assertEquals(28500000, (float) $request->estimated_total);
        $this->assertSame(2, $request->items()->count());
        $this->assertNull($request->approval_request_id);
    }

    public function test_sync_items_mengabaikan_baris_tidak_lengkap_dan_normalisasi_jumlah(): void
    {
        $request = $this->create(['items' => [
            ['asset_category_id' => '', 'item_name' => 'Tanpa kategori', 'quantity' => 1, 'estimated_unit_price' => 100],
            ['asset_category_id' => 3, 'item_name' => '   ', 'quantity' => 1, 'estimated_unit_price' => 100],
            ['asset_category_id' => 3, 'quantity' => 1],
            ['asset_category_id' => 3, 'item_name' => 'Monitor', 'quantity' => 0, 'estimated_unit_price' => 2000000],
            ['asset_category_id' => 8, 'item_name' => 'Meja'],
        ]]);

        $items = $request->items()->orderBy('id')->get();
        $this->assertSame(['Monitor', 'Meja'], $items->pluck('item_name')->all());
        $this->assertSame([1, 1], $items->pluck('quantity')->all());
        $this->assertEquals(0, (float) $items[1]->estimated_unit_price);
        $this->assertEquals(2000000, (float) $request->estimated_total);
    }

    public function test_create_dengan_departemen_eksplisit_dan_lampiran(): void
    {
        $request = $this->create(['department_id' => 3, 'attachments' => [$this->pdf('quotation.pdf')]]);

        $this->assertSame(3, $request->department_id);
        $this->assertSame(1, $request->attachments()->where('category', 'QUOTATION')->count());
    }

    public function test_draft_tanpa_item_dan_justifikasi_boleh_disimpan(): void
    {
        $request = $this->create(['items' => [], 'justification' => null]);

        $this->assertSame(ProcurementStatus::Draft, $request->status);
        $this->assertSame('', (string) $request->justification);
        $this->assertEquals(0, (float) $request->estimated_total);
    }

    public function test_create_submit_membuat_approval_ke_manager(): void
    {
        $request = $this->create([], true);

        $this->assertSame(ProcurementStatus::PendingApproval, $request->status);
        $this->assertNotNull($request->submitted_at);
        $this->assertSame(4, $request->approvalRequest->steps()->sole()->approver_employee_id);
        Notification::assertSentTo($this->user('manager.it'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan approval baru');
    }

    public function test_create_submit_tanpa_item_ditolak_dan_dibatalkan_seluruhnya(): void
    {
        try {
            $this->create(['title' => 'PR Tanpa Item UT2', 'items' => []], true);
            $this->fail('Submit tanpa item seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('minimal satu item', $e->getMessage());
        }
        $this->assertSame(0, ProcurementRequest::where('title', 'PR Tanpa Item UT2')->count());
    }

    public function test_create_submit_tanpa_justifikasi_ditolak(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Justifikasi wajib');
        $this->create(['justification' => '   '], true);
    }

    public function test_update_draft_mengganti_item_dan_menghitung_ulang_total(): void
    {
        $request = $this->create();
        $staff = $this->user('staff');
        $this->service()->update($request, [
            'title' => 'Pengadaan Revisi',
            'justification' => 'Revisi kebutuhan',
            'items' => [['asset_category_id' => 3, 'item_name' => 'Monitor 27"', 'quantity' => 4, 'estimated_unit_price' => 3000000]],
        ], $staff, false);

        $request->refresh();
        $this->assertSame(ProcurementStatus::Draft, $request->status);
        $this->assertSame('Pengadaan Revisi', $request->title);
        $this->assertSame(2, $request->department_id, 'Departemen dipertahankan bila tidak dikirim');
        $this->assertNull($request->needed_by);
        $this->assertSame(['Monitor 27"'], $request->items()->pluck('item_name')->all());
        $this->assertEquals(12000000, (float) $request->estimated_total);
    }

    public function test_update_dengan_submit_langsung_diajukan(): void
    {
        $request = $this->create(['items' => [], 'justification' => '']);
        $this->service()->update($request, ['title' => 'Lengkap', 'justification' => 'Sudah lengkap', 'items' => $this->items()], $this->user('staff'), true);

        $this->assertSame(ProcurementStatus::PendingApproval, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->approval_request_id);
    }

    public function test_update_selain_draft_ditolak(): void
    {
        $request = $this->create([], true);

        try {
            $this->service()->update($request, ['title' => 'Ubah setelah diajukan', 'items' => []], $this->user('staff'), false);
            $this->fail('Update setelah diajukan seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('mengubah procurement', $e->getMessage());
        }
        $request->refresh();
        $this->assertSame('Pengadaan Tim IT', $request->title);
        $this->assertSame(2, $request->items()->count());
    }

    public function test_submit_hanya_dari_draft(): void
    {
        $request = $this->create([], true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mengajukan procurement');
        $this->service()->submit($request, $this->user('staff'));
    }

    // ------------------------------------------------------------ cancel / approve / reject

    public function test_cancel_draft_dan_pending(): void
    {
        $draft = $this->create();
        $this->service()->cancel($draft);
        $this->assertSame(ProcurementStatus::Cancelled, $draft->fresh()->status);

        $pending = $this->create([], true);
        $this->service()->cancel($pending);
        $pending->refresh();
        $this->assertSame(ProcurementStatus::Cancelled, $pending->status);
        $this->assertNotNull($pending->cancelled_at);
        $this->assertSame(ApprovalStatus::Cancelled, $pending->approvalRequest->status);
    }

    public function test_cancel_setelah_disetujui_tanpa_alasan_ditolak(): void
    {
        $request = $this->create([], true);
        $this->approve($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Alasan pembatalan wajib diisi');
        $this->service()->cancel($request->fresh(), $this->user('staff'), 'abc');
    }

    public function test_cancel_setelah_disetujui_dengan_alasan_oleh_petugas(): void
    {
        $request = $this->create([], true);
        $this->approve($request);
        $admin = $this->user('admin.aset');

        $this->service()->cancel($request->fresh(), $admin, 'Kebutuhan dibatalkan manajemen');

        $request->refresh();
        $this->assertSame(ProcurementStatus::Cancelled, $request->status);
        $this->assertSame('Kebutuhan dibatalkan manajemen', $request->cancel_reason);
        $this->assertSame($admin->id, $request->cancelled_by_user_id);
        $this->assertNotNull($request->cancelled_at);
        $this->assertSame('APPROVED', $request->approvalRequest->status->value, 'Keputusan approval tetap tercatat');
        $this->assertDatabaseHas('audit_logs', ['action' => 'cancelled_after_approval', 'auditable_id' => $request->id]);
        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Permintaan dibatalkan');
    }

    public function test_approve_dan_reject(): void
    {
        $approved = $this->create([], true);
        $this->approve($approved);
        $this->assertSame(ProcurementStatus::Approved, $approved->fresh()->status);
        Notification::assertSentTo($this->user('admin.aset'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Procurement siap dipesan');

        $rejected = $this->create([], true);
        $this->reject($rejected, 'Anggaran belum tersedia');
        $this->assertSame(ProcurementStatus::Rejected, $rejected->fresh()->status);
    }

    // ------------------------------------------------------------ order

    public function test_order_mencatat_vendor_po_dan_pemesan(): void
    {
        $request = $this->create([], true);
        $this->approve($request);
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $this->service()->order($request, ['vendor_id' => 1, 'po_number' => 'PO-UT-777', 'ordered_at' => now()->subDay()->toDateTimeString()]);

        $request->refresh();
        $this->assertSame(ProcurementStatus::Ordered, $request->status);
        $this->assertSame(1, $request->vendor_id);
        $this->assertSame('PO-UT-777', $request->po_number);
        $this->assertSame($admin->id, $request->ordered_by_user_id);
        $this->assertSame(now()->subDay()->toDateString(), $request->ordered_at->toDateString());
    }

    public function test_order_tanpa_tanggal_memakai_waktu_sekarang(): void
    {
        $request = $this->ordered();

        $this->assertTrue($request->ordered_at->isToday());
    }

    public function test_order_selain_approved_ditolak(): void
    {
        $request = $this->create([], true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('memproses pemesanan');
        $this->service()->order($request, ['vendor_id' => 1, 'po_number' => 'PO-X']);
    }

    // ------------------------------------------------------------ receive

    public function test_receive_sebelum_dipesan_ditolak(): void
    {
        $request = $this->create([], true);
        $this->approve($request);
        [$laptop] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('mencatat penerimaan');
        $this->receive($request, [$laptop->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => 'UT2-SN-A']]);
    }

    public function test_receive_tanpa_jumlah_ditolak(): void
    {
        $request = $this->ordered();
        [$laptop, $chair] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('minimal pada satu item');
        $this->receive($request, [$laptop->id => ['accepted' => 0, 'rejected' => 0], $chair->id => ['accepted' => '']]);
    }

    public function test_receive_item_milik_procurement_lain_ditolak(): void
    {
        $request = $this->ordered();
        $other = $this->create();
        [$foreign] = $this->itemsOf($other);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Item procurement tidak valid');
        $this->receive($request, [$foreign->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => 'UT2-SN-A']]);
    }

    public function test_receive_melebihi_sisa_ditolak(): void
    {
        $request = $this->ordered();
        [, $chair] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('melebihi sisa (3 unit)');
        $this->receive($request, [$chair->id => ['accepted' => 4, 'location_id' => 2]]);
    }

    public function test_jumlah_nomor_seri_harus_sama_dengan_unit_diterima(): void
    {
        $request = $this->ordered();
        [$laptop] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('(1) harus sama dengan unit diterima (2)');
        $this->receive($request, [$laptop->id => ['accepted' => 2, 'location_id' => 2, 'serial_numbers' => 'UT2-SN-A']]);
    }

    public function test_kategori_wajib_serial_tanpa_nomor_seri_ditolak(): void
    {
        $request = $this->ordered();
        [$laptop] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('wajib mencatat nomor seri');
        $this->receive($request, [$laptop->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => " \n , "]]);
    }

    public function test_nomor_seri_duplikat_dalam_satu_baris_ditolak(): void
    {
        $request = $this->ordered();
        [$laptop] = $this->itemsOf($request);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('duplikat atau sudah terdaftar');
        $this->receive($request, [$laptop->id => ['accepted' => 2, 'location_id' => 2, 'serial_numbers' => "UT2-SN-A\nUT2-SN-A"]]);
    }

    public function test_nomor_seri_yang_sudah_terdaftar_ditolak(): void
    {
        $request = $this->ordered();
        [$laptop] = $this->itemsOf($request);
        $existing = $this->makeAsset(['asset_category_id' => 1, 'serial_number' => 'UT2-SN-EXIST']);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('duplikat atau sudah terdaftar');
        $this->receive($request, [$laptop->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => $existing->serial_number]]);
    }

    public function test_nomor_seri_duplikat_antar_baris_penerimaan_ditolak_dan_dibatalkan(): void
    {
        $request = $this->ordered([
            ['asset_category_id' => 1, 'item_name' => 'Laptop A', 'quantity' => 1, 'estimated_unit_price' => 1000],
            ['asset_category_id' => 1, 'item_name' => 'Laptop B', 'quantity' => 1, 'estimated_unit_price' => 1000],
        ]);
        [$a, $b] = $this->itemsOf($request);

        try {
            $this->receive($request, [
                $a->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => 'UT2-SN-SAMA'],
                $b->id => ['accepted' => 1, 'location_id' => 2, 'serial_numbers' => 'UT2-SN-SAMA'],
            ]);
            $this->fail('Nomor seri ganda antar baris seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Laptop B', $e->getMessage());
        }
        // Seluruh penerimaan di-rollback
        $this->assertSame(0, Asset::where('serial_number', 'UT2-SN-SAMA')->count());
        $this->assertSame(0, $request->receipts()->count());
        $this->assertSame(ProcurementStatus::Ordered, $request->fresh()->status);
        $this->assertSame(0, $a->fresh()->quantity_received);
    }

    public function test_receive_sebagian_membuat_aset_dan_status_partially_received(): void
    {
        $request = $this->ordered();
        [$laptop, $chair] = $this->itemsOf($request);
        $receipt = $this->receive($request, [
            $laptop->id => [
                'accepted' => 2, 'rejected' => 0, 'location_id' => 2, 'unit_cost' => 11900000, 'condition' => 'GOOD',
                'serial_numbers' => " UT2-SN-001 \r\nUT2-SN-002,", 'brand' => 'Dell', 'model' => 'Latitude', 'warranty_end_date' => today()->addYears(2)->toDateString(),
            ],
            $chair->id => ['accepted' => 1, 'rejected' => 1, 'location_id' => 4, 'exception_notes' => '1 unit patah kaki'],
        ], ['attachments' => [$this->pdf('surat-jalan.pdf')]]);

        $request->refresh();
        $this->assertSame(ProcurementStatus::PartiallyReceived, $request->status);
        $this->assertNull($request->completed_at);
        $this->assertMatchesRegularExpression('/^GR-\d{4}-\d{5}$/', $receipt->receipt_no);
        $this->assertSame(1, $receipt->attachments()->where('category', 'RECEIPT')->count());
        $this->assertSame(2, $receipt->items()->count());

        $this->assertSame(2, $laptop->fresh()->quantity_received);
        $this->assertSame(1, $chair->fresh()->quantity_received);
        $this->assertSame(1, $chair->fresh()->quantity_rejected);
        $this->assertSame('1 unit patah kaki', $receipt->items()->where('procurement_request_item_id', $chair->id)->value('exception_notes'));

        $laptops = Asset::where('procurement_request_item_id', $laptop->id)->orderBy('id')->get();
        $this->assertSame(['UT2-SN-001', 'UT2-SN-002'], $laptops->pluck('serial_number')->all());
        foreach ($laptops as $asset) {
            $this->assertMatchesRegularExpression('/^LPT-\d{4}-\d{5}$/', $asset->asset_tag);
            $this->assertSame(AssetStatus::Available, $asset->status);
            $this->assertSame(2, $asset->location_id);
            $this->assertSame(1, $asset->vendor_id);
            $this->assertSame(2, $asset->department_id);
            $this->assertSame($receipt->id, $asset->procurement_receipt_id);
            $this->assertEquals(11900000, (float) $asset->purchase_cost);
            $this->assertSame('Dell', $asset->brand);
            $this->assertSame('i7/16GB', $asset->specification);
            $event = $this->lastEvent($asset);
            $this->assertSame(AssetEventType::Received, $event->event_type);
            $this->assertSame(AssetStatus::Available, $event->to_status);
            $this->assertSame('procurement_receipt', $event->reference_type);
            $this->assertSame($receipt->id, $event->reference_id);
        }
        $this->assertNotSame($laptops[0]->asset_tag, $laptops[1]->asset_tag);

        $chairAsset = Asset::where('procurement_request_item_id', $chair->id)->sole();
        $this->assertMatchesRegularExpression('/^FUR-\d{4}-\d{5}$/', $chairAsset->asset_tag);
        $this->assertNull($chairAsset->serial_number);
        $this->assertSame(AssetCondition::Good, $chairAsset->condition);
        $this->assertEquals(1500000, (float) $chairAsset->purchase_cost, 'Harga estimasi dipakai bila unit_cost kosong');

        Notification::assertSentTo($this->user('staff'), AppNotification::class, fn (AppNotification $n) => $n->title === 'Barang procurement diterima');
    }

    public function test_receive_bertahap_hingga_lengkap_menjadi_received(): void
    {
        $request = $this->ordered();
        [$laptop, $chair] = $this->itemsOf($request);
        $this->receive($request, [$chair->id => ['accepted' => 3, 'location_id' => 2]]);
        $this->assertSame(ProcurementStatus::PartiallyReceived, $request->fresh()->status);

        // Sisa laptop 2 unit; menerima 3 ditolak
        try {
            $this->receive($request, [$laptop->id => ['accepted' => 3, 'location_id' => 2, 'serial_numbers' => 'UT2-A,UT2-B,UT2-C']]);
            $this->fail('Melebihi sisa seharusnya ditolak');
        } catch (BusinessRuleException) {
        }
        $this->receive($request, [$laptop->id => ['accepted' => 2, 'location_id' => 2, 'serial_numbers' => 'UT2-A,UT2-B']]);

        $request->refresh();
        $this->assertSame(ProcurementStatus::Received, $request->status);
        $this->assertNotNull($request->completed_at);
        $this->assertSame(2, $request->receipts()->count());
        $this->assertSame(5, Asset::whereIn('procurement_request_item_id', [$laptop->id, $chair->id])->count());

        $this->expectException(BusinessRuleException::class);
        $this->receive($request, [$chair->id => ['accepted' => 0, 'rejected' => 1]]);
    }

    public function test_receive_hanya_ditolak_tidak_membuat_aset(): void
    {
        $request = $this->ordered();
        [$laptop] = $this->itemsOf($request);
        $this->receive($request, [$laptop->id => ['accepted' => 0, 'rejected' => 2, 'exception_notes' => 'Salah spesifikasi']]);

        $this->assertSame(ProcurementStatus::PartiallyReceived, $request->fresh()->status);
        $this->assertSame(0, Asset::where('procurement_request_item_id', $laptop->id)->count());
        $this->assertSame(0, $laptop->fresh()->quantity_received);
        $this->assertSame(2, $laptop->fresh()->quantity_rejected);
    }

    // ------------------------------------------------------------ close

    public function test_close_procurement_yang_diterima_sebagian(): void
    {
        $request = $this->ordered();
        [, $chair] = $this->itemsOf($request);
        $this->receive($request, [$chair->id => ['accepted' => 3, 'location_id' => 2]]);
        $this->service()->close($request, 'Laptop dibatalkan vendor');

        $request->refresh();
        $this->assertSame(ProcurementStatus::Received, $request->status);
        $this->assertSame('Laptop dibatalkan vendor', $request->closing_note);
        $this->assertNotNull($request->completed_at);
    }

    public function test_close_selain_partially_received_ditolak(): void
    {
        $request = $this->ordered();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('menutup procurement');
        $this->service()->close($request, 'Tutup');
    }
}
