<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'employee_no', 'name', 'email', 'phone', 'department_id', 'manager_employee_id', 'job_title',
    'employment_status', 'start_date', 'end_date', 'work_location_id',
])]
class Employee extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'employment_status' => EmploymentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_employee_id');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'work_location_id')->withTrashed();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_at');
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(AssetLoan::class, 'borrower_employee_id')->active();
    }

    /**
     * Label seragam untuk pilihan karyawan di dropdown:
     * "Nama (No. Karyawan) - Jabatan, Departemen". Muat relasi department agar tidak N+1.
     */
    public function optionLabel(): string
    {
        $detail = collect([$this->job_title, $this->department?->name])->filter()->implode(', ');

        return "{$this->name} ({$this->employee_no})".($detail !== '' ? " - {$detail}" : '');
    }

    public function isActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('employment_status', EmploymentStatus::Active->value);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->employee_no})";
    }

    /** Apakah $candidateId berada di rantai atasan karyawan ini (mencegah siklus manager). */
    public function hasInManagerChain(int $candidateId): bool
    {
        $visited = [];
        $current = $this->manager;
        while ($current && ! in_array($current->id, $visited, true)) {
            if ($current->id === $candidateId) {
                return true;
            }
            $visited[] = $current->id;
            $current = $current->manager;
        }

        return false;
    }
}
