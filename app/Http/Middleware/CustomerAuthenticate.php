<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('web')->check()) {
            return redirect()->route('login')->with('intent', $request->fullUrl());
        }

        $user = auth('web')->user();

        if (! $user->is_active) {
            auth('web')->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        return $next($request);
    }
}
