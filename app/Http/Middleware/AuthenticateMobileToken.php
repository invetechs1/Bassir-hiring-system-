<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        if ($bearer === '' || ! str_contains($bearer, '|')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        [$tokenId, $secret] = explode('|', $bearer, 2);
        if (! ctype_digit((string) $tokenId) || trim($secret) === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = MobileAccessToken::with('user')->find((int) $tokenId);
        if (! $token || ! $token->isActive()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hash = hash('sha256', $secret);
        if (! hash_equals((string) $token->token_hash, $hash)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! $token->user || ! $token->user->is_active) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('mobile_access_token', $token);
        $request->setUserResolver(fn () => $token->user);
        Auth::setUser($token->user);

        return $next($request);
    }
}

