<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'label',
        'value',
        'type',
        'options',
        'description',
        'sort_order',
        'is_core',
    ];

    protected $casts = [
        'options' => 'array',
        'is_core' => 'boolean',
    ];

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => is_numeric($setting->value) ? (int) $setting->value : $default,
            default => $setting->value ?? $default,
        };
    }
}
