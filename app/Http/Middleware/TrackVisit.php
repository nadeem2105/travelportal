<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsSession;
use App\Services\Crm\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures an analytics_sessions row exists for the current visitor session and
 * keeps its last-activity + last-touch attribution fresh. Runs AFTER
 * CaptureAttribution (so the session already holds first/last-touch UTM). Cheap:
 * one insert on the first page of a session, one lightweight update thereafter,
 * both keyed on the unique session_id. Never blocks or breaks page rendering.
 *
 * It does NOT count page views or client events — those arrive via the ingestion
 * API (consent-gated) so we don't double-count. IP is stored hashed only.
 */
class TrackVisit
{
    public function __construct(protected AttributionService $attribution) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! config('services.analytics.backend_enabled', true)) {
                return $response;
            }
            if (! $request->isMethod('get')
                || $request->is('admin', 'admin/*', 'api/*', 'agent', 'agent/*')
                || $request->ajax()
                || ! $request->hasSession()
                || ! Schema::hasTable('analytics_sessions')) {
                return $response;
            }

            // Canonical analytics id in a CLIENT-READABLE cookie (not the auth
            // session id) so the browser can send the same id to the ingestion API.
            $sessionId = $request->cookie('lz_sid');
            $isNew = ! $sessionId;
            if ($isNew) {
                $sessionId = (string) Str::uuid();
                Cookie::queue('lz_sid', $sessionId, 60 * 24 * 30); // 30 days
            }

            $stored = (array) $request->session()->get((string) config('crm.attribution.session_key', 'crm_attribution'), []);
            $last = (array) ($stored['last'] ?? []);
            $first = (array) ($stored['first'] ?? []);

            if ($isNew) {
                // First page of the session → create the row.
                $ua = (string) $request->userAgent();
                $isReturning = $request->cookie('lz_returning') === '1';

                AnalyticsSession::updateOrCreate(
                    ['session_id' => $sessionId],
                    array_filter([
                        'user_id' => auth('web')->id(),
                        'is_returning' => $isReturning,
                        'device_type' => $this->deviceType($ua),
                        'browser' => $this->browser($ua),
                        'os' => $this->os($ua),
                        'language' => substr((string) $request->getPreferredLanguage(), 0, 20) ?: null,
                        'ip_hash' => $this->hashIp($request->ip()),
                        'user_agent' => substr($ua, 0, 512),
                        'landing_page' => substr($request->fullUrl(), 0, 1000),
                        'referrer' => substr((string) $request->headers->get('referer', ''), 0, 1000) ?: null,
                        'channel' => $this->channel($request, $last),
                        'first_touch' => $first ?: null,
                        'last_touch' => $last ?: null,
                        'utm_source' => $last['utm_source'] ?? null,
                        'utm_medium' => $last['utm_medium'] ?? null,
                        'utm_campaign' => $last['utm_campaign'] ?? null,
                        'utm_term' => $last['utm_term'] ?? null,
                        'utm_content' => $last['utm_content'] ?? null,
                        'gclid' => $last['gclid'] ?? null,
                        'fbclid' => $last['fbclid'] ?? null,
                        'started_at' => now(),
                        'last_activity_at' => now(),
                    ], fn ($v) => $v !== null && $v !== '') + ['session_id' => $sessionId, 'is_returning' => $isReturning, 'started_at' => now(), 'last_activity_at' => now()]
                );

                // Mark the browser as "seen" for 1 year → returning-visitor detection.
                Cookie::queue('lz_returning', '1', 60 * 24 * 365);
            } else {
                // Subsequent page → refresh last activity + last-touch (cheap update).
                AnalyticsSession::where('session_id', $sessionId)->update(array_filter([
                    'user_id' => auth('web')->id(),
                    'last_activity_at' => now(),
                    'last_touch' => $last ?: null,
                    'utm_source' => $last['utm_source'] ?? null,
                    'utm_medium' => $last['utm_medium'] ?? null,
                    'utm_campaign' => $last['utm_campaign'] ?? null,
                ], fn ($v) => $v !== null) + ['last_activity_at' => now()]);
            }
        } catch (\Throwable $e) {
            // Analytics must never break a page.
            report($e);
        }

        return $response;
    }

    protected function channel(Request $request, array $last): string
    {
        $medium = strtolower((string) ($last['utm_medium'] ?? ''));
        $source = strtolower((string) ($last['utm_source'] ?? ''));
        $ref = strtolower((string) $request->headers->get('referer', ''));

        if (! empty($last['gclid']) || in_array($medium, ['cpc', 'ppc', 'paid', 'paidsearch', 'display'], true)) {
            return 'paid';
        }
        if (! empty($last['fbclid']) || $medium === 'social'
            || in_array($source, ['facebook', 'instagram', 'meta', 'twitter', 'x', 'linkedin', 'youtube', 'tiktok'], true)) {
            return 'social';
        }
        if ($medium === 'email' || $source === 'newsletter') {
            return 'email';
        }
        if ($medium === 'referral' || ($ref !== '' && ! $this->isSearchEngine($ref) && ! str_contains($ref, (string) $request->getHost()))) {
            return 'referral';
        }
        if ($this->isSearchEngine($ref) || $medium === 'organic') {
            return 'organic';
        }

        return 'direct';
    }

    protected function isSearchEngine(string $ref): bool
    {
        foreach (['google.', 'bing.com', 'yahoo.', 'duckduckgo.', 'ecosia.', 'baidu.', 'yandex.'] as $se) {
            if (str_contains($ref, $se)) {
                return true;
            }
        }

        return false;
    }

    protected function deviceType(string $ua): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobile|iP(hone|od)|Android.*Mobile|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function browser(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/Edg/i', $ua) => 'Edge',
            (bool) preg_match('/OPR|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => null,
        };
    }

    protected function os(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iOS/i', $ua) => 'iOS',
            (bool) preg_match('/Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => null,
        };
    }

    protected function hashIp(?string $ip): ?string
    {
        return $ip ? hash('sha256', $ip . config('app.key')) : null;
    }
}
