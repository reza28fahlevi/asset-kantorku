<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\Like;

#[Fillable([
    'asset_tag', 'name', 'asset_category_id', 'procurement_request_item_id', 'procurement_receipt_id',
    'vendor_id', 'department_id', 'location_id', 'brand', 'model', 'serial_number', 'specification',
    'status', 'condition', 'purchase_date', 'purchase_cost', 'warranty_end_date', 'notes',
])]
class Asset extends Model
{
    use Auditable, HasAttachments;

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'purchase_date' => 'date',
            'warranty_end_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_at');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class)->orderByDesc('checked_out_at');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(AssetLoan::class)->active();
    }

    public function disposalRequests(): HasMany
    {
        return $this->hasMany(DisposalRequest::class)->orderByDesc('id');
    }

    /** Pemegang saat ini (penerima assignment aktif atau peminjam aktif). */
    public function currentHolder(): ?Employee
    {
        return $this->activeAssignment?->employee ?? $this->activeLoan?->borrower;
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', AssetStatus::Available->value);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }
        // Escape karakter khusus LIKE, termasuk backslash (escape default PostgreSQL) agar term dicari apa adanya.
        $like = Like::contains($term);
        $query->where(fn (Builder $q) => $q
            ->where('asset_tag', 'ilike', $like)
            ->orWhere('name', 'ilike', $like)
            ->orWhere('serial_number', 'ilike', $like)
            ->orWhere('brand', 'ilike', $like)
            ->orWhere('model', 'ilike', $like));
    }

    public function isDisposed(): bool
    {
        return $this->status === AssetStatus::Disposed;
    }

    public function isWarrantyExpiringSoon(int $days = 30): bool
    {
        // Dibandingkan per tanggal: garansi yang berakhir hari ini masih dihitung "segera berakhir"
        return $this->warranty_end_date !== null
            && $this->warranty_end_date->copy()->startOfDay()->gte(today())
            && today()->diffInDays($this->warranty_end_date->copy()->startOfDay()) <= $days;
    }
}
