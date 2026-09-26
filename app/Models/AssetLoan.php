<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\LoanStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'asset_id', 'borrower_employee_id', 'asset_loan_request_id', 'usage_location_id', 'checked_out_at',
    'checked_out_by_user_id', 'due_at', 'original_due_at', 'returned_at', 'returned_by_user_id',
    'condition_out', 'condition_in', 'checkout_notes', 'return_notes', 'is_late', 'status',
])]
class AssetLoan extends Model
{
    use Auditable, HasAttachments;

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'condition_out' => AssetCondition::class,
            'condition_in' => AssetCondition::class,
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'original_due_at' => 'datetime',
            'returned_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'borrower_employee_id');
    }

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(AssetLoanRequest::class, 'asset_loan_request_id');
    }

    public function usageLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'usage_location_id')->withTrashed();
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by_user_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(LoanExtensionRequest::class)->orderByDesc('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [LoanStatus::CheckedOut->value, LoanStatus::Overdue->value]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [LoanStatus::CheckedOut, LoanStatus::Overdue], true);
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_at->isPast();
    }
}
