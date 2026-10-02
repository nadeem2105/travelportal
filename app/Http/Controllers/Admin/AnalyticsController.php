<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Admin website-analytics dashboard. Server-renders the overview + all tabs for
 * the selected period (with period-over-period comparison), and exposes small
 * JSON endpoints for the real-time panel and on-demand series. All figures come
 * from AnalyticsQueryService (real data only).
 */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsQueryService $q) {}

    public function index(Request $request)
    {
        [$from, $to, $rangeKey] = $this->resolveRange($request);
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $overview = $this->q->overview($from, $to);
        $previous = $this->q->overview($prevFrom, $prevTo);

        return view('admin.analytics.index', [
            'range' => $rangeKey,
            'from' => $from,
            'to' => $to,
            'overview' => $overview,
            'previous' => $previous,
            'deltas' => $this->deltas($overview, $previous),
            'series' => $this->q->timeseries($from, $to),
            'sources' => $this->q->sources($from, $to),
            'devices' => $this->q->devices($from, $to),
            'funnel' => $this->q->funnel($from, $to),
            'topPages' => $this->q->topPages($from, $to),
            'topPackages' => $this->q->topPackages($from, $to),
            'topDestinations' => $this->q->topDestinations($from, $to),
            'campaigns' => $this->q->campaigns($from, $to),
        ]);
    }

    /** Live snapshot (polled by the dashboard). */
    public function realtime()
    {
        return response()->json($this->q->realtime());
    }

    /* --------------------------------------------------------------- helpers */

    /** @return array{0:Carbon,1:Carbon,2:string} */
    private function resolveRange(Request $request): array
    {
        $key = $request->query('range', 'last_7');
        $now = Carbon::now();

        return match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'yesterday'],
            'last_30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'last_30'],
            'last_90' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), 'last_90'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'this_month'],
            'prev_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'prev_month'],
            'custom' => $this->customRange($request),
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'last_7'],
        };
    }

    private function customRange(Request $request): array
    {
        try {
            $from = Carbon::parse($request->query('from'))->startOfDay();
            $to = Carbon::parse($request->query('to'))->endOfDay();
            if ($from > $to) [$from, $to] = [$to, $from];
        } catch (\Throwable $e) {
            $from = Carbon::now()->subDays(6)->startOfDay();
            $to = Carbon::now()->endOfDay();
        }

        return [$from, $to, 'custom'];
    }

    /** @return array{0:Carbon,1:Carbon} equal-length period immediately before. */
    private function previousPeriod(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;

        return [$from->copy()->subDays($days)->startOfDay(), $from->copy()->subDay()->endOfDay()];
    }

    private function deltas(array $now, array $prev): array
    {
        $out = [];
        foreach ($now as $k => $v) {
            if (! is_numeric($v)) {
                continue;
            }
            $p = (float) ($prev[$k] ?? 0);
            $out[$k] = $p > 0 ? round((($v - $p) / $p) * 100, 1) : ($v > 0 ? 100.0 : 0.0);
        }

        return $out;
    }
}
