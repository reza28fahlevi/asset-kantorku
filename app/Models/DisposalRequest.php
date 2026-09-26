<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalType;
use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
use App\Enums\DisposalStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\InteractsWithApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_no', 'approval_request_id', 'asset_id', 'requester_employee_id', 'created_by_user_id',
    'reason_type', 'reason', 'condition_description', 'planned_method', 'status', 'asset_status_before',
    'submitted_at', 'cancelled_at', 'executed_at', 'executed_by_user_id', 'actual_method',
    'disposal_recipient', 'proceeds_amount', 'execution_notes', 'completed_at',
])]
class DisposalRequest extends Model implements Approvable
{
    public const VIEW_ALL_PERMISSION = 'disposal.view_all';

    /** Kolom karyawan yang juga berhak melihat permintaan. */
    public const BENEFICIARY_COLUMNS = [];

    use Auditable, HasAttachments, InteractsWithApproval;

    protected function casts(): array
    {
        return [
            'status' => DisposalStatus::class,
            'reason_type' => DisposalReason::class,
            'planned_method' => DisposalMethod::class,
            'actual_method' => DisposalMethod::class,
            'asset_status_before' => AssetStatus::class,
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'executed_at' => 'date',
            'completed_at' => 'datetime',
            'proceeds_amount' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function approvalType(): ApprovalType
    {
        return ApprovalType::Disposal;
    }

    public function approvalSubjectEmployee(): Employee
    {
        return $this->requester;
    }

    public function approvalTitle(): string
    {
        return "{$this->request_no} · Disposal {$this->asset->asset_tag}";
    }

    public function approvalSummary(): array
    {
        return [
            'Pemohon' => $this->requester->name,
            'Aset' => "{$this->asset->asset_tag} {$this->asset->name}",
            'Alasan' => $this->reason_type->label().' — '.$this->reason,
            'Metode rencana' => $this->planned_method->label(),
            'Kondisi' => $this->condition_description ?? '-',
        ];
    }

    public function approvalUrl(): string
    {
        return route('disposals.show', $this);
    }
}
