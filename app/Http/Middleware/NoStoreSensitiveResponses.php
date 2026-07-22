<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreSensitiveResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $routeName = (string) ($request->route()?->getName() ?? '');
        $isSensitivePage = $request->user() !== null || in_array($routeName, ['login', 'login.form', 'login.post'], true);
        if (! $isSensitivePage) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
