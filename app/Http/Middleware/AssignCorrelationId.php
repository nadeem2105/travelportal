<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID')
            ?: $request->header('X-Request-ID')
            ?: (string) Str::uuid();

        // Bind to service container for downstream service/API consumption
        app()->instance('correlation_id', $correlationId);

        // Attach correlation ID to log context for all log entries in this request
        Log::withContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);

        // Attach header to HTTP response
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
