<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing']) || ! config('security.force_https', false) || $this->isSecureRequest($request)) {
            return $next($request);
        }

        $target = 'https://'.$request->getHttpHost().$request->getRequestUri();

        return redirect()->to($target, 308);
    }

    private function isSecureRequest(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        if (! config('security.trust_proxy_https_headers', false)) {
            return false;
        }

        $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        if (in_array('https', array_map('trim', explode(',', $forwardedProto)), true)) {
            return true;
        }

        if (strtolower((string) $request->headers->get('x-forwarded-ssl', '')) === 'on') {
            return true;
        }

        if (strtolower((string) $request->headers->get('front-end-https', '')) === 'on') {
            return true;
        }

        $cfVisitor = json_decode((string) $request->headers->get('cf-visitor', ''), true);

        return is_array($cfVisitor) && strtolower((string) ($cfVisitor['scheme'] ?? '')) === 'https';
    }
}
