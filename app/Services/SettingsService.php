<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Cached flat map of key => raw value.
     */
    protected function all(): array
    {
        return Cache::rememberForever(Setting::CACHE_KEY, function () {
            return Setting::query()->pluck('value', 'key')->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return match (Setting::query()->where('key', $key)->value('type')) {
            'boolean' => in_array($value, ['1', 'true', 'on', 'yes'], true),
            'number' => is_numeric($value) ? (str_contains((string) $value, '.') ? (float) $value : (int) $value) : $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function set(string $key, mixed $value, ?string $group = null, string $type = 'text'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'group' => $group ?? Setting::query()->where('key', $key)->value('group') ?? 'general',
                'type' => $type,
            ]
        );
    }

    public function group(string $group): array
    {
        return Setting::query()->where('group', $group)->orderBy('key')->get()->toArray();
    }

    /**
     * Company-level helper accessors used across layouts.
     */
    public function company(string $key, mixed $default = ''): mixed
    {
        return $this->get('company_' . $key, $default);
    }

    public function social(string $key): ?string
    {
        return $this->get('social_' . $key);
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_enabled', false);
    }
}
