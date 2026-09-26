<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['request_type', 'requester_employee_id', 'submitted_by_user_id', 'status', 'submitted_at', 'closed_at'])]
class ApprovalRequest extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'request_type' => ApprovalType::class,
            'status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    public function procurementRequest(): HasOne
    {
        return $this->hasOne(ProcurementRequest::class);
    }

    public function assignmentRequest(): HasOne
    {
        return $this->hasOne(AssignmentRequest::class);
    }

    public function loanRequest(): HasOne
    {
        return $this->hasOne(AssetLoanRequest::class);
    }

    public function loanExtensionRequest(): HasOne
    {
        return $this->hasOne(LoanExtensionRequest::class);
    }

    public function disposalRequest(): HasOne
    {
        return $this->hasOne(DisposalRequest::class);
    }

    /** Nama relasi yang memuat permintaan sumber berdasarkan tipe. */
    public static function subjectRelation(ApprovalType $type): string
    {
        return match ($type) {
            ApprovalType::Procurement => 'procurementRequest',
            ApprovalType::Assignment => 'assignmentRequest',
            ApprovalType::Loan => 'loanRequest',
            ApprovalType::LoanExtension => 'loanExtensionRequest',
            ApprovalType::Disposal => 'disposalRequest',
        };
    }

    /** Permintaan sumber (tepat satu per approval request). */
    public function subject(): (Model&Approvable)|null
    {
        return $this->{static::subjectRelation($this->request_type)};
    }
}
