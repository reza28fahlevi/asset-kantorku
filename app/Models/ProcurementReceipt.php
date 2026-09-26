<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['receipt_no', 'procurement_request_id', 'received_at', 'received_by_user_id', 'delivery_note_no', 'notes'])]
class ProcurementReceipt extends Model
{
    use Auditable, HasAttachments;

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementReceiptItem::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
