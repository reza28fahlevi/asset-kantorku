<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'display_name', 'description', 'is_system'])]
class Role extends Model
{
    use Auditable;

    public const STAFF = 'staff';
    public const MANAGER = 'manager';
    public const ASSET_ADMIN = 'asset_admin';
    public const SYS_ADMIN = 'sys_admin';
    public const AUDITOR = 'auditor';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
