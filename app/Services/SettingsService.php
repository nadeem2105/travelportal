<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected static ?array $memoizedSettings = null;

    /**
     * Cached flat map of key => ['value' => ..., 'type' => ...].
     */
    protected function all(): array
    {
        if (self::$memoizedSettings !== null) {
            return self::$memoizedSettings;
        }

        return self::$memoizedSettings = Cache::rememberForever(Setting::CACHE_KEY, function () {
            return Setting::query()->get(['key', 'value', 'type'])->keyBy('key')->map(fn ($s) => [
                'value' => $s->value,
                'type' => $s->type ?? 'text',
            ])->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->all()[$key] ?? null;

        if ($item === null || $item['value'] === null || $item['value'] === '') {
            return $default;
        }

        $value = $item['value'];
        $type = $item['type'] ?? 'text';

        return match ($type) {
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

        self::$memoizedSettings = null;
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
