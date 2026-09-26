<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Table(key: 'key', keyType: 'string', incrementing: false)]
#[Fillable(['key', 'value', 'description'])]
class Setting extends Model
{
    public const CREATED_AT = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('app.settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app.settings');
    }
}
