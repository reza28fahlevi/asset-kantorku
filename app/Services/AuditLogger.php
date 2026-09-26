<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

class AuditLogger
{
    public static function log(string $action, ?Model $model = null, array $old = [], array $new = []): void
    {
        $request = app()->runningInConsole() ? null : request();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'url' => $request?->fullUrl(),
            'request_id' => Context::get('request_id'),
        ]);
    }
}
