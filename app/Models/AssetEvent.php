<?php

namespace App\Models;

use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histori domain aset. Append-only (dijaga trigger database).
 */
#[Fillable([
    'asset_id', 'event_type', 'from_status', 'to_status', 'from_location_id', 'to_location_id',
    'related_employee_id', 'performed_by_employee_id', 'performed_by_user_id',
    'reference_type', 'reference_id', 'notes', 'metadata', 'occurred_at',
])]
class AssetEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event_type' => AssetEventType::class,
            'from_status' => AssetStatus::class,
            'to_status' => AssetStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id')->withTrashed();
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id')->withTrashed();
    }

    public function relatedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'related_employee_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
