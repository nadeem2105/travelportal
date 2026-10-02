<?php

namespace App\Http\Middleware;

use App\Services\Crm\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records marketing attribution (UTM / gclid / fbclid, landing page, referrer)
 * on public GET page views so it can be attached to any lead the visitor later
 * submits. First-touch is preserved; only last-touch updates. Never touches
 * admin or API traffic.
 */
class CaptureAttribution
{
    public function __construct(protected AttributionService $attribution)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get')
            && ! $request->is('admin', 'admin/*', 'api/*')
            && $request->hasSession()
            && ! $request->ajax()) {
            try {
                $this->attribution->capture($request);
            } catch (\Throwable $e) {
                // Attribution must never break page rendering.
                report($e);
            }
        }

        return $next($request);
    }
}
