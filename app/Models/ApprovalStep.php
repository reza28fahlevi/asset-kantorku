<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'approval_request_id', 'step_order', 'approver_employee_id', 'approver_source',
    'status', 'comment', 'decided_at', 'decided_by_user_id',
])]
class ApprovalStep extends Model
{
    use Auditable;

    public const SOURCE_MANAGER = 'MANAGER';
    public const SOURCE_ESCALATION = 'ESCALATION';

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'step_order' => 'integer',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }
}
