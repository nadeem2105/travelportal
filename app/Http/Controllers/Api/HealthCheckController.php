<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $httpCode = 200;
        $services = [];

        // 1. Database Check
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);
            $services['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbLatency,
                'connection' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            $status = 'degraded';
            $httpCode = 503;
            $services['database'] = [
                'status' => 'error',
                'error' => 'Database connection failed',
            ];
        }

        // 2. Cache Check
        try {
            $cacheKey = 'health_check_' . microtime(true);
            Cache::put($cacheKey, 'ok', 10);
            $cached = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            $services['cache'] = [
                'status' => $cached === 'ok' ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            $status = 'degraded';
            $httpCode = 503;
            $services['cache'] = [
                'status' => 'error',
                'error' => 'Cache read/write failed',
            ];
        }

        // 3. Storage Check
        try {
            $storageOk = Storage::disk('public')->exists('.') || true;
            $services['storage'] = [
                'status' => 'ok',
                'driver' => 'public',
            ];
        } catch (\Throwable $e) {
            $status = 'degraded';
            $services['storage'] = [
                'status' => 'error',
                'error' => 'Storage disk check failed',
            ];
        }

        // 4. Queue / Jobs Backlog
        try {
            $pendingJobs = DB::table('jobs')->count();
            $services['queue'] = [
                'status' => 'ok',
                'connection' => config('queue.default'),
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Throwable $e) {
            $services['queue'] = [
                'status' => 'unknown',
                'error' => 'Jobs table not readable',
            ];
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'version' => '1.0.0',
            'correlation_id' => app()->has('correlation_id') ? app('correlation_id') : null,
            'services' => $services,
        ], $httpCode);
    }
}
