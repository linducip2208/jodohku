<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value', 'type', 'description'];

    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        $cacheKey = $group.'.'.$key;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $setting = static::where('group', $group)->where('key', $key)->first();

        if (! $setting) {
            // Cache the miss too: unconfigured keys otherwise re-query on
            // every read (hundreds of queries per discovery run).
            return self::$cache[$cacheKey] = $default;
        }

        return self::$cache[$cacheKey] = self::castValue($setting->value, $setting->type);
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): Setting
    {
        $setting = static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value), 'type' => $type]
        );

        self::$cache[$group.'.'.$key] = self::castValue($setting->value, $setting->type);

        return $setting;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'array', 'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}
