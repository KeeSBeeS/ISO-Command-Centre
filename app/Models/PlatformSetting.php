<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'description'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (!Schema::hasTable('platform_settings')) {
            return $default;
        }

        $value = static::where('key', $key)->value('value');
        return $value === null ? $default : $value;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): void
    {
        if (!Schema::hasTable('platform_settings')) {
            return;
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
            ]
        );
    }
}
