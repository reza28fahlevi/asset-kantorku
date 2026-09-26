<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat create/update/delete model ke audit_logs secara otomatis.
 */
trait Auditable
{
    /** Kolom yang tidak dicatat ke audit log. */
    protected static array $auditExcept = ['password', 'remember_token', 'created_at', 'updated_at'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, [], static::auditFilter($model->getAttributes(), $model));
        });

        static::updated(function (Model $model) {
            $changes = static::auditFilter($model->getChanges(), $model);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLogger::log('updated', $model, static::auditFilter($old, $model), $changes);
        });

        static::deleted(function (Model $model) {
            AuditLogger::log('deleted', $model, static::auditFilter($model->getAttributes(), $model), []);
        });
    }

    protected static function auditFilter(array $attributes, Model $model): array
    {
        $except = array_merge(static::$auditExcept, $model->getHidden());
        $filtered = array_diff_key($attributes, array_flip($except));

        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $filtered);
    }
}
