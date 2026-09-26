<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'attachable_type', 'attachable_id', 'category', 'original_name', 'stored_path',
    'mime_type', 'size_bytes', 'uploaded_by_user_id',
])]
class Attachment extends Model
{
    public const CATEGORIES = [
        'QUOTATION' => 'Quotation / Penawaran',
        'RECEIPT' => 'Dokumen Penerimaan',
        'HANDOVER' => 'Bukti Serah-Terima',
        'RETURN' => 'Bukti Pengembalian',
        'DISPOSAL_EVIDENCE' => 'Bukti Disposal',
        'OTHER' => 'Lainnya',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function humanSize(): string
    {
        $size = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1).' '.$unit;
            }
            $size /= 1024;
        }

        return round($size, 1).' TB';
    }
}
