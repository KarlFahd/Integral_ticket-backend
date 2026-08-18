<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the Cloudflare tunnel (and any other reverse proxy in front of
        // this app) to report the real scheme/host via X-Forwarded-* headers.
        // Without this, Laravel thinks every request is plain HTTP (since
        // `php artisan serve` itself only ever speaks HTTP), so route()/url()
        // generate http:// links even when the browser is on https:// — which
        // is exactly what breaks Passport's OAuth authorize/approve forms
        // behind the tunnel (browser flags them as insecure, and the
        // http->https redirect drops the POST body, including the CSRF and
        // auth tokens).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
