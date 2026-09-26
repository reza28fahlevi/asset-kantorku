<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'procurement_request_id', 'asset_category_id', 'item_name', 'specification', 'quantity',
    'estimated_unit_price', 'quantity_received', 'quantity_rejected',
])]
class ProcurementRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_received' => 'integer',
            'quantity_rejected' => 'integer',
            'estimated_unit_price' => 'decimal:2',
        ];
    }

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id')->withTrashed();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }

    public function subtotal(): float
    {
        return $this->quantity * (float) $this->estimated_unit_price;
    }
}
