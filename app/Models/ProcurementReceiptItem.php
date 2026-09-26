<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['procurement_receipt_id', 'procurement_request_item_id', 'quantity_accepted', 'quantity_rejected', 'exception_notes'])]
class ProcurementReceiptItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_accepted' => 'integer',
            'quantity_rejected' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(ProcurementReceipt::class, 'procurement_receipt_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id');
    }
}
