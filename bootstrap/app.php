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
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trustedProxies !== '') {
            if ($trustedProxies === '*') {
                $middleware->trustProxies(at: '*');
            } else {
                $middleware->trustProxies(
                    at: array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
                );
            }
        }

        $trustedHostsRaw = trim((string) env('TRUSTED_HOSTS', ''));
        if ($trustedHostsRaw === '') {
            $trustedHostsRaw = (string) (parse_url((string) env('APP_URL', ''), PHP_URL_HOST) ?: '');
        }
        if ($trustedHostsRaw !== '') {
            $trustedHosts = array_values(array_filter(array_map(static function (string $host): string {
                $host = trim($host);
                if ($host === '') {
                    return '';
                }

                return '^'.preg_quote($host, '/').'$';
            }, explode(',', $trustedHostsRaw))));
            if ($trustedHosts !== []) {
                $middleware->trustHosts(at: $trustedHosts);
            }
        }

        $middleware->web(append: [
            \App\Http\Middleware\AddRequestContext::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\NoStoreSensitiveResponses::class,
            \App\Http\Middleware\ForceHttps::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'force_password_change' => \App\Http\Middleware\EnsurePasswordChange::class,
            'set_locale' => \App\Http\Middleware\SetLocale::class,
            'mobile_token' => \App\Http\Middleware\AuthenticateMobileToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
