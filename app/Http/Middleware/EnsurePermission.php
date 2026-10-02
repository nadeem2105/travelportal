<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side authorization for every sensitive admin action.
 * Frontend menu hiding is cosmetic only — this middleware enforces.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        if (! $admin->can($permission)) {
            ActivityLogger::log('denied', 'auth', "Blocked access requiring [{$permission}]");

            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
