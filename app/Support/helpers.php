<?php

use App\Services\SettingsService;

if (! function_exists('settings')) {
    /**
     * Admin-managed settings access from any view/controller.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('money')) {
    /**
     * Format an INR amount like the design: ₹24,999 (no decimals unless needed).
     */
    function money(float|int|string|null $amount, bool $decimals = false): string
    {
        $amount = (float) ($amount ?? 0);

        return '₹' . number_format($amount, $decimals || fmod($amount, 1.0) !== 0.0 ? 2 : 0);
    }
}

if (! function_exists('img')) {
    /**
     * Resolve an image reference which may be a full URL, a storage path,
     * or a bundled asset path. Falls back to a bundled scene image.
     */
    function img(?string $path, string $fallback = 'images/hero.svg'): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return asset($fallback);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'images/')) {
            return asset($path);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        }

        return asset($fallback);
    }
}

if (! function_exists('hero_banner')) {
    /**
     * Resolve the site-wide hero banner URL managed in Admin → Homepage (the
     * `hero` HomepageSection image). Used across the public listing pages
     * (home/flights/hotels/cabs/packages) so one admin change updates them all.
     * Resolved once per request; falls back to the given asset if unset.
     */
    function hero_banner(string $fallback = 'images/hero.svg'): string
    {
        static $cached = null;

        if ($cached === null) {
            try {
                $image = \App\Models\HomepageSection::where('type', 'hero')->value('image');
            } catch (\Throwable $e) {
                $image = null; // DB not ready (e.g. during migrate) — use fallback.
            }
            $cached = img($image, $fallback);
        }

        return $cached;
    }
}

if (! function_exists('status_pill_class')) {
    function status_pill_class(string $status): string
    {
        return match ($status) {
            'confirmed', 'completed', 'captured', 'approved', 'active', 'resolved', 'subscribed', 'published', 'open' => 'bg-emerald-100 text-emerald-700',
            'pending', 'payment_pending', 'created', 'requested', 'in_progress', 'draft', 'waiting', 'initiated', 'scheduled' => 'bg-amber-100 text-amber-700',
            'failed', 'cancelled', 'rejected', 'closed', 'unsubscribed', 'inactive' => 'bg-rose-100 text-rose-700',
            'refunded', 'refund_initiated', 'refund_requested' => 'bg-violet-100 text-violet-700',
            'payment_success_booking_failed' => 'bg-orange-100 text-orange-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}

if (! function_exists('label_case')) {
    function label_case(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
