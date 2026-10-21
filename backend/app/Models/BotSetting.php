<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * App\Models\BotSetting
 *
 * Configurable system settings stored in the database.
 * Allows dynamic runtime adjustments without hardcoding values in code.
 *
 * @property int    $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BotSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting value by key, with caching for optimal performance.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $data = Cache::remember("bot_setting:{$key}", 5, function () use ($key) {
                $row = static::where('key', $key)->first();
                return $row ? ['value' => $row->value, 'type' => $row->type] : null;
            });

            if (!$data) {
                return $default;
            }

            return match ($data['type']) {
                'integer', 'int' => (int) $data['value'],
                'boolean', 'bool' => filter_var($data['value'], FILTER_VALIDATE_BOOLEAN),
                'float', 'double' => (float) $data['value'],
                'json', 'array'   => json_decode($data['value'], true),
                default           => $data['value'],
            };
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Set or update a setting value by key and invalidate cache.
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value'       => is_array($value) ? json_encode($value) : (string) $value,
                'type'        => $type,
                'description' => $description,
            ]
        );

        Cache::forget("bot_setting:{$key}");

        return $setting;
    }
}
