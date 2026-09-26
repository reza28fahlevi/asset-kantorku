<?php

namespace App\Services;

use App\Contracts\ApprovalHandler;
use App\Contracts\Approvable;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Services\Concerns\GuardsWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProcurementService implements ApprovalHandler
{
    use GuardsWorkflow;

    public function __construct(
        private ApprovalService $approvals,
        private AssetService $assets,
        private NumberGenerator $numbers,
        private AttachmentService $attachments,
        private Notifier $notifier,
    ) {
    }

    public function create(array $data, User $user, bool $submit): ProcurementRequest
    {
        $employee = $this->actingEmployee($user);

        return DB::transaction(function () use ($data, $user, $employee, $submit) {
            $request = ProcurementRequest::create([
                'request_no' => $this->numbers->requestNumber('PR'),
                'requester_employee_id' => $employee->id,
                'created_by_user_id' => $user->id,
                'department_id' => $data['department_id'] ?? $employee->department_id,
                'title' => $data['title'],
                'justification' => $data['justification'] ?? '',
                'needed_by' => $data['needed_by'] ?? null,
                'estimated_total' => 0,
                'status' => ProcurementStatus::Draft,
            ]);

            $this->syncItems($request, $data['items'] ?? []);

            $this->attachments->store($request, $data['attachments'] ?? null, 'QUOTATION');

            if ($submit) {
                $this->submit($request, $user);
            }

            return $request;
        });
    }

    /** Perbarui draft (item diganti seluruhnya), opsional langsung diajukan. */
    public function update(ProcurementRequest $request, array $data, User $user, bool $submit): ProcurementRequest
    {
        return DB::transaction(function () use ($request, $data, $user, $submit) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::Draft], 'mengubah procurement');

            $request->update([
                'department_id' => $data['department_id'] ?? $request->department_id,
                'title' => $data['title'],
                'justification' => $data['justification'] ?? '',
                'needed_by' => $data['needed_by'] ?? null,
            ]);

            $request->items()->delete();
            $this->syncItems($request, $data['items'] ?? []);
            $this->attachments->store($request, $data['attachments'] ?? null, 'QUOTATION');

            if ($submit) {
                $this->submit($request, $user);
            }

            return $request;
        });
    }

    /** Simpan baris item yang lengkap dan hitung ulang total estimasi. */
    private function syncItems(ProcurementRequest $request, array $items): void
    {
        $items = collect($items)
            ->filter(fn ($i) => ! empty($i['asset_category_id']) && filled($i['item_name'] ?? null))
            ->map(fn ($i) => [
                'asset_category_id' => $i['asset_category_id'],
                'item_name' => $i['item_name'],
                'specification' => $i['specification'] ?? null,
                'quantity' => max(1, (int) ($i['quantity'] ?? 1)),
                'estimated_unit_price' => (float) ($i['estimated_unit_price'] ?? 0),
            ]);

        $items->each(fn ($item) => $request->items()->create($item));
        $request->update(['estimated_total' => $items->sum(fn ($i) => $i['quantity'] * $i['estimated_unit_price'])]);
    }

    public function submit(ProcurementRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::Draft], 'mengajukan procurement');
            if ($request->items()->doesntExist()) {
                throw new BusinessRuleException('Procurement harus memiliki minimal satu item.');
            }
            if (blank($request->justification)) {
                throw new BusinessRuleException('Justifikasi wajib diisi sebelum diajukan.');
            }

            $request->update(['status' => ProcurementStatus::PendingApproval, 'submitted_at' => now()]);
            $this->approvals->submit($request, $request->requester, $user);
        });
    }

    public function cancel(ProcurementRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::Draft, ProcurementStatus::PendingApproval], 'membatalkan procurement');

            $this->approvals->cancel($request->approvalRequest);
            $request->update(['status' => ProcurementStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    public function onApproved(Model&Approvable $subject, User $decidedBy): void
    {
        /** @var ProcurementRequest $subject */
        $subject->update(['status' => ProcurementStatus::Approved]);

        $this->notifier->toPermission(
            'procurement.order',
            'Procurement siap dipesan',
            "{$subject->request_no} telah disetujui dan menunggu pemesanan.",
            route('procurements.show', $subject),
            'bi-cart-check',
        );
    }

    public function onRejected(Model&Approvable $subject, User $decidedBy, string $comment): void
    {
        /** @var ProcurementRequest $subject */
        $subject->update(['status' => ProcurementStatus::Rejected]);
    }

    /** Administrator mencatat pemesanan: vendor & nomor PO. */
    public function order(ProcurementRequest $request, array $data): void
    {
        DB::transaction(function () use ($request, $data) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::Approved], 'memproses pemesanan');

            $request->update([
                'vendor_id' => $data['vendor_id'],
                'po_number' => $data['po_number'],
                'ordered_at' => $data['ordered_at'] ?? now(),
                'ordered_by_user_id' => auth()->id(),
                'status' => ProcurementStatus::Ordered,
            ]);
        });
    }

    /**
     * Penerimaan barang: setiap unit yang diterima menjadi record aset dengan asset tag unik.
     *
     * $data['items'][<item_id>] = [accepted, rejected, exception_notes, serial_numbers, location_id,
     *                              unit_cost, warranty_end_date, condition, brand, model]
     */
    public function receive(ProcurementRequest $request, array $data, User $user): ProcurementReceipt
    {
        return DB::transaction(function () use ($request, $data, $user) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::Ordered, ProcurementStatus::PartiallyReceived], 'mencatat penerimaan');

            $items = $request->items()->with('category')->lockForUpdate()->get()->keyBy('id');
            $lines = collect($data['items'] ?? [])->filter(fn ($l) => (int) ($l['accepted'] ?? 0) > 0 || (int) ($l['rejected'] ?? 0) > 0);
            if ($lines->isEmpty()) {
                throw new BusinessRuleException('Isi jumlah diterima atau ditolak minimal pada satu item.');
            }

            $receipt = ProcurementReceipt::create([
                'receipt_no' => $this->numbers->requestNumber('GR'),
                'procurement_request_id' => $request->id,
                'received_at' => $data['received_at'],
                'received_by_user_id' => $user->id,
                'delivery_note_no' => $data['delivery_note_no'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $itemId => $line) {
                $item = $items->get($itemId) ?? throw new BusinessRuleException('Item procurement tidak valid.');
                $accepted = (int) ($line['accepted'] ?? 0);
                $rejected = (int) ($line['rejected'] ?? 0);

                if ($accepted > $item->remainingQuantity()) {
                    throw new BusinessRuleException("Jumlah diterima untuk \"{$item->item_name}\" melebihi sisa ({$item->remainingQuantity()} unit).");
                }

                $serials = collect(preg_split('/\r\n|\r|\n|,/', (string) ($line['serial_numbers'] ?? '')))
                    ->map(fn ($s) => trim($s))->filter()->values();
                if ($serials->isNotEmpty() && $serials->count() !== $accepted) {
                    throw new BusinessRuleException("Jumlah nomor seri \"{$item->item_name}\" ({$serials->count()}) harus sama dengan unit diterima ({$accepted}).");
                }
                if ($item->category->requires_serial && $accepted > 0 && $serials->isEmpty()) {
                    throw new BusinessRuleException("Kategori {$item->category->name} wajib mencatat nomor seri untuk setiap unit.");
                }
                if ($serials->duplicates()->isNotEmpty() || Asset::whereIn('serial_number', $serials)->exists()) {
                    throw new BusinessRuleException("Nomor seri pada \"{$item->item_name}\" duplikat atau sudah terdaftar.");
                }

                $receipt->items()->create([
                    'procurement_request_item_id' => $item->id,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'exception_notes' => $line['exception_notes'] ?? null,
                ]);

                for ($i = 0; $i < $accepted; $i++) {
                    $asset = Asset::create([
                        'asset_tag' => $this->numbers->assetTag($item->category->code),
                        'name' => $item->item_name,
                        'asset_category_id' => $item->asset_category_id,
                        'procurement_request_item_id' => $item->id,
                        'procurement_receipt_id' => $receipt->id,
                        'vendor_id' => $request->vendor_id,
                        'department_id' => $request->department_id,
                        'location_id' => $line['location_id'],
                        'brand' => $line['brand'] ?? null,
                        'model' => $line['model'] ?? null,
                        'serial_number' => $serials[$i] ?? null,
                        'specification' => $item->specification,
                        'status' => AssetStatus::Available,
                        'condition' => $line['condition'] ?? AssetCondition::Good->value,
                        'purchase_date' => $data['received_at'],
                        'purchase_cost' => $line['unit_cost'] ?? $item->estimated_unit_price,
                        'warranty_end_date' => $line['warranty_end_date'] ?? null,
                    ]);

                    $this->assets->recordEvent($asset, AssetEventType::Received, [
                        'to_status' => AssetStatus::Available,
                        'to_location_id' => $asset->location_id,
                        'reference_type' => 'procurement_receipt',
                        'reference_id' => $receipt->id,
                        'notes' => "Penerimaan {$receipt->receipt_no} dari {$request->request_no}",
                    ]);
                }

                $item->increment('quantity_received', $accepted);
                $item->increment('quantity_rejected', $rejected);
            }

            $this->attachments->store($receipt, $data['attachments'] ?? null, 'RECEIPT');

            $request->load('items');
            $request->update($request->isFullyReceived()
                ? ['status' => ProcurementStatus::Received, 'completed_at' => now()]
                : ['status' => ProcurementStatus::PartiallyReceived]);

            $this->notifier->toEmployee(
                $request->requester,
                'Barang procurement diterima',
                "Penerimaan {$receipt->receipt_no} untuk {$request->request_no} telah dicatat.",
                route('procurements.show', $request),
                'bi-box-seam',
            );

            return $receipt;
        });
    }

    /** Tutup procurement yang diterima sebagian setelah selisih diselesaikan. */
    public function close(ProcurementRequest $request, string $note): void
    {
        DB::transaction(function () use ($request, $note) {
            $request = ProcurementRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->ensureStatus($request->status, [ProcurementStatus::PartiallyReceived], 'menutup procurement');

            $request->update([
                'status' => ProcurementStatus::Received,
                'completed_at' => now(),
                'closing_note' => $note,
            ]);
        });
    }
}
