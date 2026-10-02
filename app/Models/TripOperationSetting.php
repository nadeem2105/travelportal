<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TripOperationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** Sensible operational defaults (used when no row exists). */
    public const DEFAULTS = [
        'auto_generate_itinerary' => true,
        'driver_reminder_offsets' => ['1_day', '2_hours'], // 1_day|12_hours|2_hours
        'send_customer_itinerary_on_generate' => true,
        'send_tomorrow_plan' => false,
        'tomorrow_plan_time' => '19:00',
        'notify_driver_channels' => ['whatsapp'],   // whatsapp|sms
        'notify_customer_channels' => ['whatsapp', 'email'],
    ];

    public static function get(string $key, $default = null)
    {
        $all = self::allSettings();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function allSettings(): array
    {
        return Cache::remember('trip_operation_settings', 300, function () {
            $stored = self::query()->pluck('value', 'key')->toArray();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public static function put(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('trip_operation_settings');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('trip_operation_settings'));
        static::deleted(fn () => Cache::forget('trip_operation_settings'));
    }
}
