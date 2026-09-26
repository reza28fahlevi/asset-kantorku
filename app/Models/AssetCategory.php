<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'description', 'useful_life_months', 'requires_serial', 'is_active'])]
class AssetCategory extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_serial' => 'boolean',
            'useful_life_months' => 'integer',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isInUse(): bool
    {
        return $this->assets()->exists()
            || ProcurementRequestItem::where('asset_category_id', $this->id)->exists();
    }
}
