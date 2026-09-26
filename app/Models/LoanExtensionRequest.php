<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalType;
use App\Enums\ExtensionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\InteractsWithApproval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_no', 'approval_request_id', 'asset_loan_id', 'requester_employee_id', 'created_by_user_id',
    'current_due_at', 'requested_due_at', 'reason', 'status', 'applied_at', 'cancelled_at',
])]
class LoanExtensionRequest extends Model implements Approvable
{
    public const VIEW_ALL_PERMISSION = 'loan.view_all';

    /** Kolom karyawan yang juga berhak melihat permintaan. */
    public const BENEFICIARY_COLUMNS = [];

    use Auditable, InteractsWithApproval;

    protected function casts(): array
    {
        return [
            'status' => ExtensionStatus::class,
            'current_due_at' => 'datetime',
            'requested_due_at' => 'datetime',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(AssetLoan::class, 'asset_loan_id');
    }

    public function approvalType(): ApprovalType
    {
        return ApprovalType::LoanExtension;
    }

    /** Approver = manager peminjam. */
    public function approvalSubjectEmployee(): Employee
    {
        return $this->loan->borrower;
    }

    public function approvalTitle(): string
    {
        return "{$this->request_no} · Perpanjangan {$this->loan->asset->asset_tag}";
    }

    public function approvalSummary(): array
    {
        return [
            'Peminjam' => $this->loan->borrower->display_name,
            'Aset' => "{$this->loan->asset->asset_tag} {$this->loan->asset->name}",
            'Due date saat ini' => $this->current_due_at->translatedFormat('d M Y'),
            'Due date diminta' => $this->requested_due_at->translatedFormat('d M Y'),
            'Alasan' => $this->reason,
        ];
    }

    public function approvalUrl(): string
    {
        return route('loans.show', $this->loan->asset_loan_request_id ?? 0).'#loan-'.$this->asset_loan_id;
    }
}
