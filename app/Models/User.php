<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['employee_id', 'name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditable, Notifiable;

    /** Cache permission selama satu request. */
    protected ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    // ------------------------------------------------------------------ RBAC

    /** @return Collection<int, string> nama permission milik user (gabungan semua role). */
    public function permissionNames(): Collection
    {
        return $this->permissionCache ??= Permission::query()
            ->whereHas('roles.users', fn ($q) => $q->whereKey($this->getKey()))
            ->pluck('name');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return $this->permissionNames()->intersect($permissions)->isNotEmpty();
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** User dapat bertindak sebagai approver bila aktif, tertaut karyawan, dan memiliki permission approval. */
    public function isApprover(): bool
    {
        // Wajib: akun aktif, tertaut karyawan yang status kepegawaiannya AKTIF, dan punya permission approval
        return $this->is_active
            && $this->employee_id !== null
            && $this->employee?->isActive() === true
            && $this->hasPermission('approval.decide');
    }
}
