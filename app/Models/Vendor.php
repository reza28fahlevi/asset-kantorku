<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'contact_person', 'phone', 'email', 'address', 'tax_number', 'notes', 'is_active'])]
class Vendor extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        return ProcurementRequest::where('vendor_id', $this->id)->exists()
            || Asset::where('vendor_id', $this->id)->exists();
    }
}
