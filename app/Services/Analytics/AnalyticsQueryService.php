<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\CrmLead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-side aggregations for the admin analytics dashboard. Every figure comes
 * from real data: analytics_sessions / analytics_page_views / analytics_events
 * for behaviour + attribution, and the authoritative bookings / crm_leads tables
 * for conversions + revenue (never duplicated or mocked). All methods are
 * range-bounded and defensive (empty results before the migration runs).
 */
class AnalyticsQueryService
{
    private function ready(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Headline KPIs for a period. */
    public function overview(Carbon $from, Carbon $to): array
    {
        $sessions = $newUsers = $returning = $pageViews = 0;
        if ($this->ready('analytics_sessions')) {
            $sessions = DB::table('analytics_sessions')->whereBetween('started_at', [$from, $to])->count();
            $newUsers = DB::table('analytics_sessions')->whereBetween('started_at', [$from, $to])->where('is_returning', false)->count();
            $returning = $sessions - $newUsers;
            $pageViews = (int) DB::table('analytics_sessions')->whereBetween('started_at', [$from, $to])->sum('page_views');
        }
        if ($this->ready('analytics_page_views')) {
            $pv = DB::table('analytics_page_views')->whereBetween('created_at', [$from, $to])->count();
            $pageViews = max($pageViews, $pv);
        }

        $leads = CrmLead::whereBetween('created_at', [$from, $to])->count();
        $bookings = Booking::whereBetween('booked_at', [$from, $to])->whereIn('status', ['confirmed', 'completed'])->count();
        $revenue = (float) Booking::whereBetween('booked_at', [$from, $to])->whereIn('status', ['confirmed', 'completed'])->sum('total_amount');

        $convRate = $sessions > 0 ? round(($bookings / $sessions) * 100, 2) : 0.0;
        $leadConvRate = $leads > 0 ? round(($bookings / $leads) * 100, 2) : 0.0;
        $avgValue = $bookings > 0 ? round($revenue / $bookings, 2) : 0.0;

        return [
            'sessions' => $sessions,
            'users' => $sessions, // one session ≈ one visitor window here
            'new_users' => $newUsers,
            'returning_users' => max(0, $returning),
            'page_views' => $pageViews,
            'leads' => $leads,
            'bookings' => $bookings,
            'revenue' => $revenue,
            'conversion_rate' => $convRate,
            'lead_conversion_rate' => $leadConvRate,
            'avg_booking_value' => $avgValue,
        ];
    }

    /** Daily series for sessions / bookings / revenue. */
    public function timeseries(Carbon $from, Carbon $to): array
    {
        $labels = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor <= $end) {
            $labels[$cursor->format('Y-m-d')] = 0;
            $cursor->addDay();
        }

        $sessions = $labels;
        if ($this->ready('analytics_sessions')) {
            foreach (DB::table('analytics_sessions')
                ->selectRaw('DATE(started_at) d, COUNT(*) c')
                ->whereBetween('started_at', [$from, $to])->groupBy('d')->get() as $r) {
                if (isset($sessions[$r->d])) $sessions[$r->d] = (int) $r->c;
            }
        }

        $bookings = $labels;
        $revenue = $labels;
        foreach (Booking::selectRaw('DATE(booked_at) d, COUNT(*) c, SUM(total_amount) rev')
            ->whereBetween('booked_at', [$from, $to])->whereIn('status', ['confirmed', 'completed'])
            ->groupBy('d')->get() as $r) {
            if (isset($bookings[$r->d])) $bookings[$r->d] = (int) $r->c;
            if (isset($revenue[$r->d])) $revenue[$r->d] = round((float) $r->rev, 2);
        }

        return [
            'labels' => array_keys($labels),
            'sessions' => array_values($sessions),
            'bookings' => array_values($bookings),
            'revenue' => array_values($revenue),
        ];
    }

    /** Traffic by channel with sessions + attributed revenue (via purchase events). */
    public function sources(Carbon $from, Carbon $to): array
    {
        if (! $this->ready('analytics_sessions')) return [];

        $sessions = DB::table('analytics_sessions')
            ->selectRaw("COALESCE(NULLIF(channel,''),'direct') channel, COUNT(*) sessions")
            ->whereBetween('started_at', [$from, $to])
            ->groupBy('channel')->pluck('sessions', 'channel')->all();

        $revenue = [];
        if ($this->ready('analytics_events')) {
            $rows = DB::table('analytics_events as e')
                ->join('analytics_sessions as s', 's.session_id', '=', 'e.session_id')
                ->selectRaw("COALESCE(NULLIF(s.channel,''),'direct') channel, SUM(e.value) rev")
                ->where('e.event_type', 'booking_confirmed')
                ->whereBetween('e.created_at', [$from, $to])
                ->groupBy('channel')->get();
            foreach ($rows as $r) $revenue[$r->channel] = round((float) $r->rev, 2);
        }

        $out = [];
        foreach ($sessions as $channel => $count) {
            $out[] = [
                'channel' => $channel,
                'sessions' => (int) $count,
                'revenue' => $revenue[$channel] ?? 0.0,
            ];
        }
        usort($out, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

        return $out;
    }

    /** Device / browser split. */
    public function devices(Carbon $from, Carbon $to): array
    {
        if (! $this->ready('analytics_sessions')) return ['devices' => [], 'browsers' => []];

        $devices = DB::table('analytics_sessions')
            ->selectRaw("COALESCE(NULLIF(device_type,''),'unknown') k, COUNT(*) c")
            ->whereBetween('started_at', [$from, $to])->groupBy('k')->pluck('c', 'k')->all();

        $browsers = DB::table('analytics_sessions')
            ->selectRaw("COALESCE(NULLIF(browser,''),'Other') k, COUNT(*) c")
            ->whereBetween('started_at', [$from, $to])->groupBy('k')->orderByDesc('c')->limit(6)->pluck('c', 'k')->all();

        return ['devices' => $devices, 'browsers' => $browsers];
    }

    /** Top pages by views. */
    public function topPages(Carbon $from, Carbon $to, int $limit = 10): array
    {
        if (! $this->ready('analytics_page_views')) return [];

        return DB::table('analytics_page_views')
            ->selectRaw('path, COUNT(*) views, COUNT(DISTINCT session_id) sessions, ROUND(AVG(NULLIF(time_on_page,0))) avg_time')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('path')
            ->groupBy('path')->orderByDesc('views')->limit($limit)->get()
            ->map(fn ($r) => [
                'path' => $r->path,
                'views' => (int) $r->views,
                'sessions' => (int) $r->sessions,
                'avg_time' => (int) ($r->avg_time ?? 0),
            ])->all();
    }

    /** Top packages: views + enquiries + bookings + revenue (real joins). */
    public function topPackages(Carbon $from, Carbon $to, int $limit = 10): array
    {
        if (! $this->ready('analytics_events') || ! Schema::hasTable('packages')) return [];

        $views = DB::table('analytics_events')
            ->selectRaw('product_id, COUNT(*) c')
            ->where('event_type', 'view_package')->whereNotNull('product_id')
            ->whereBetween('created_at', [$from, $to])->groupBy('product_id')->pluck('c', 'product_id')->all();

        $starts = DB::table('analytics_events')
            ->selectRaw('product_id, COUNT(*) c')
            ->where('event_type', 'book_package')->whereNotNull('product_id')
            ->whereBetween('created_at', [$from, $to])->groupBy('product_id')->pluck('c', 'product_id')->all();

        $bookings = Booking::selectRaw('product_id, COUNT(*) c, SUM(total_amount) rev')
            ->where('product_type', 'package')->whereIn('status', ['confirmed', 'completed'])
            ->whereBetween('booked_at', [$from, $to])->groupBy('product_id')->get()->keyBy('product_id');

        $ids = array_unique(array_merge(array_keys($views), array_keys($starts), $bookings->keys()->all()));
        if (! $ids) return [];

        $names = DB::table('packages')->whereIn('id', $ids)->pluck('name', 'id');

        $rows = [];
        foreach ($ids as $id) {
            $bk = $bookings->get($id);
            $v = (int) ($views[$id] ?? 0);
            $b = (int) ($bk->c ?? 0);
            $rows[] = [
                'id' => $id,
                'name' => $names[$id] ?? ('#' . $id),
                'views' => $v,
                'enquiries' => (int) ($starts[$id] ?? 0),
                'bookings' => $b,
                'revenue' => round((float) ($bk->rev ?? 0), 2),
                'conversion_rate' => $v > 0 ? round(($b / $v) * 100, 1) : 0.0,
            ];
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['views'] <=> $a['views']);

        return array_slice($rows, 0, $limit);
    }

    /** Top destinations by views + searches. */
    public function topDestinations(Carbon $from, Carbon $to, int $limit = 10): array
    {
        if (! $this->ready('analytics_events') || ! Schema::hasTable('destinations')) return [];

        $views = DB::table('analytics_events')
            ->selectRaw('product_id, COUNT(*) c')
            ->where('event_type', 'view_destination')->whereNotNull('product_id')
            ->whereBetween('created_at', [$from, $to])->groupBy('product_id')->pluck('c', 'product_id')->all();

        if (! $views) return [];
        $names = DB::table('destinations')->whereIn('id', array_keys($views))->pluck('name', 'id');

        $rows = [];
        foreach ($views as $id => $c) {
            $rows[] = ['id' => $id, 'name' => $names[$id] ?? ('#' . $id), 'views' => (int) $c];
        }
        usort($rows, fn ($a, $b) => $b['views'] <=> $a['views']);

        return array_slice($rows, 0, $limit);
    }

    /** Full booking funnel with step-to-step conversion. */
    public function funnel(Carbon $from, Carbon $to): array
    {
        $ev = fn (array $types) => $this->ready('analytics_events')
            ? DB::table('analytics_events')->whereIn('event_type', $types)->whereBetween('created_at', [$from, $to])->count()
            : 0;

        $sessions = $this->ready('analytics_sessions')
            ? DB::table('analytics_sessions')->whereBetween('started_at', [$from, $to])->count() : 0;

        $steps = [
            ['key' => 'sessions', 'label' => 'Visitors', 'count' => $sessions],
            ['key' => 'search', 'label' => 'Search', 'count' => $ev(['search_flight', 'search_hotel', 'search_cab', 'travel_search'])],
            ['key' => 'product_view', 'label' => 'Product View', 'count' => $ev(['view_package', 'view_hotel', 'view_item'])],
            ['key' => 'booking_start', 'label' => 'Booking Start', 'count' => $ev(['book_package', 'booking_start'])],
            ['key' => 'checkout', 'label' => 'Checkout', 'count' => $ev(['begin_checkout'])],
            ['key' => 'payment', 'label' => 'Payment', 'count' => $ev(['payment_initiated'])],
            ['key' => 'booking', 'label' => 'Booking', 'count' => Booking::whereBetween('booked_at', [$from, $to])->whereIn('status', ['confirmed', 'completed'])->count()],
        ];

        $prev = null;
        foreach ($steps as &$s) {
            $s['rate'] = ($prev !== null && $prev > 0) ? round(($s['count'] / $prev) * 100, 1) : null;
            $prev = $s['count'];
        }

        return $steps;
    }

    /** Campaign performance (sessions, leads, bookings, revenue). ROAS when spend is known. */
    public function campaigns(Carbon $from, Carbon $to, int $limit = 15): array
    {
        if (! $this->ready('analytics_sessions')) return [];

        $sessions = DB::table('analytics_sessions')
            ->selectRaw("utm_campaign, COALESCE(NULLIF(utm_source,''),'—') utm_source, COUNT(*) sessions")
            ->whereBetween('started_at', [$from, $to])
            ->whereNotNull('utm_campaign')->where('utm_campaign', '!=', '')
            ->groupBy('utm_campaign', 'utm_source')->get();

        $revenue = [];
        if ($this->ready('analytics_events')) {
            foreach (DB::table('analytics_events as e')
                ->join('analytics_sessions as s', 's.session_id', '=', 'e.session_id')
                ->selectRaw('s.utm_campaign, SUM(e.value) rev, COUNT(*) bookings')
                ->where('e.event_type', 'booking_confirmed')
                ->whereBetween('e.created_at', [$from, $to])
                ->whereNotNull('s.utm_campaign')
                ->groupBy('s.utm_campaign')->get() as $r) {
                $revenue[$r->utm_campaign] = ['rev' => round((float) $r->rev, 2), 'bookings' => (int) $r->bookings];
            }
        }

        $leads = CrmLead::selectRaw('utm_campaign, COUNT(*) c')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('utm_campaign')->groupBy('utm_campaign')->pluck('c', 'utm_campaign')->all();

        $rows = [];
        foreach ($sessions as $r) {
            $rev = $revenue[$r->utm_campaign] ?? ['rev' => 0.0, 'bookings' => 0];
            $rows[] = [
                'campaign' => $r->utm_campaign,
                'source' => $r->utm_source,
                'sessions' => (int) $r->sessions,
                'leads' => (int) ($leads[$r->utm_campaign] ?? 0),
                'bookings' => $rev['bookings'],
                'revenue' => $rev['rev'],
                'roas' => null, // requires ad spend; wired when marketing spend is available
            ];
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue'] ?: $b['sessions'] <=> $a['sessions']);

        return array_slice($rows, 0, $limit);
    }

    /** Live snapshot: active users, current pages, recent events. */
    public function realtime(int $windowMinutes = 5): array
    {
        $since = now()->subMinutes($windowMinutes);

        $active = $this->ready('analytics_sessions')
            ? DB::table('analytics_sessions')->where('last_activity_at', '>=', $since)->count() : 0;

        $pages = $this->ready('analytics_page_views')
            ? DB::table('analytics_page_views')->selectRaw('path, COUNT(*) c')
                ->where('created_at', '>=', $since)->whereNotNull('path')
                ->groupBy('path')->orderByDesc('c')->limit(8)->get()
                ->map(fn ($r) => ['path' => $r->path, 'count' => (int) $r->c])->all()
            : [];

        $events = $this->ready('analytics_events')
            ? DB::table('analytics_events')->select('event_type', 'product_type', 'created_at')
                ->where('created_at', '>=', $since)->orderByDesc('id')->limit(15)->get()
                ->map(fn ($r) => ['event' => $r->event_type, 'product' => $r->product_type, 'at' => (string) $r->created_at])->all()
            : [];

        return ['active_users' => $active, 'pages' => $pages, 'events' => $events, 'window' => $windowMinutes];
    }
}
