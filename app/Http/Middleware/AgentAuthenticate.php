<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('agent')->check()) {
            return redirect()->route('agent.login')->with('intent', $request->fullUrl());
        }

        $agent = auth('agent')->user();

        if ($agent->status === 'suspended') {
            auth('agent')->logout();

            return redirect()->route('agent.login')->withErrors([
                'email' => 'Your agent account has been suspended. Please contact support.',
            ]);
        }

        if (! $agent->isApproved()) {
            auth('agent')->logout();

            return redirect()->route('agent.login')->withErrors([
                'email' => 'Your agent application is not yet approved. We will notify you once it is reviewed.',
            ]);
        }

        return $next($request);
    }
}
