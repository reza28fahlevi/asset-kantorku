<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'employee_id', 'assignment_request_id', 'location_id', 'assigned_at', 'assigned_by_user_id',
    'condition_out', 'handover_document_no', 'handover_notes', 'returned_at', 'returned_by_user_id',
    'condition_in', 'return_notes',
])]
class AssetAssignment extends Model
{
    use Auditable, HasAttachments;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
            'condition_out' => AssetCondition::class,
            'condition_in' => AssetCondition::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignmentRequest(): BelongsTo
    {
        return $this->belongsTo(AssignmentRequest::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->returned_at === null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('returned_at');
    }
}
