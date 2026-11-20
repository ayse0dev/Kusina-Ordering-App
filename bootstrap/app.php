<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Caddy/Nginx/a load balancer: trust X-Forwarded-* so https URLs, secure cookies and IPs are right.
        // TRUSTED_PROXIES = '*' or a comma-separated list of proxy IPs. Leave unset when not behind a proxy.
        $proxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(at: $proxies === '*' ? '*' : ($proxies ? array_map('trim', explode(',', $proxies)) : null));

        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        // Signature-verified server-to-server callback; no browser session/CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/stripe', 'webhooks/courier/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
