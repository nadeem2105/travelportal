<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse-proxy / tunnel headers (ngrok, Cloudflare, load balancers)
        // so Laravel detects HTTPS and generates correct https asset/link URLs.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->append(\App\Http\Middleware\AssignCorrelationId::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Capture marketing attribution on public page views (CRM), then mirror it
        // onto the analytics session. TrackVisit runs after CaptureAttribution so the
        // first/last-touch UTM is already in the session.
        $middleware->web(append: [
            \App\Http\Middleware\CaptureAttribution::class,
            \App\Http\Middleware\TrackVisit::class,
        ]);

        // External webhooks (payments, WhatsApp) carry no CSRF token; they are
        // authenticated by provider signature / verify token instead.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,
            'admin.permission' => \App\Http\Middleware\EnsurePermission::class,
            'customer.auth' => \App\Http\Middleware\CustomerAuthenticate::class,
            'agent.auth' => \App\Http\Middleware\AgentAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Method Not Allowed',
                    'message' => 'The ' . $request->method() . ' method is not supported for this API route. Please use the appropriate HTTP method.',
                ], 405);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'Unauthorized: A valid Bearer token is required to access this API route.',
                ], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Not Found',
                    'message' => 'The requested API route does not exist.',
                ], 404);
            }
        });
    })->create();
