<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalType;
use App\Enums\ProcurementStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\InteractsWithApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_no', 'approval_request_id', 'requester_employee_id', 'created_by_user_id', 'department_id',
    'title', 'justification', 'needed_by', 'estimated_total', 'status', 'vendor_id', 'po_number',
    'ordered_at', 'ordered_by_user_id', 'submitted_at', 'completed_at', 'closing_note', 'cancelled_at',
])]
class ProcurementRequest extends Model implements Approvable
{
    public const VIEW_ALL_PERMISSION = 'procurement.view_all';

    /** Kolom karyawan yang juga berhak melihat permintaan. */
    public const BENEFICIARY_COLUMNS = [];

    use Auditable, HasAttachments, InteractsWithApproval;

    protected function casts(): array
    {
        return [
            'status' => ProcurementStatus::class,
            'needed_by' => 'date',
            'estimated_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class)->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProcurementReceipt::class)->orderByDesc('id');
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn (ProcurementRequestItem $item) => $item->remainingQuantity() === 0);
    }

    // ------------------------------------------------------------ Approvable

    public function approvalType(): ApprovalType
    {
        return ApprovalType::Procurement;
    }

    public function approvalSubjectEmployee(): Employee
    {
        return $this->requester;
    }

    public function approvalTitle(): string
    {
        return "{$this->request_no} · {$this->title}";
    }

    public function approvalSummary(): array
    {
        return [
            'Pemohon' => $this->requester->name,
            'Departemen' => $this->department->name,
            'Jumlah item' => $this->items->sum('quantity').' unit',
            'Estimasi total' => 'Rp '.number_format((float) $this->estimated_total, 0, ',', '.'),
            'Justifikasi' => $this->justification,
        ];
    }

    public function approvalUrl(): string
    {
        return route('procurements.show', $this);
    }
}
