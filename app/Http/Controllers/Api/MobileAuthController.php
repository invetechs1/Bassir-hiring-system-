<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class MobileAuthController extends Controller
{
    public function login(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:160'],
            'password' => ['required', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $throttleKey = 'mobile-login:'.Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $audit->log(null, 'MOBILE_LOGIN_THROTTLED', 'users', null, [
                'username_hash' => hash('sha256', Str::lower($data['username'])),
                'wait_seconds' => $seconds,
            ], $request);

            return response()->json([
                'message' => "Too many login attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()
            ->where($field, $data['username'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            $audit->log(null, 'MOBILE_LOGIN_FAILED', 'users', null, [
                'username_hash' => hash('sha256', Str::lower($data['username'])),
                'field' => $field,
            ], $request);

            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        RateLimiter::clear($throttleKey);

        $ttlDays = max(1, min(90, (int) config('bassir.mobile_token_ttl_days', 30)));
        try {
            $secret = bin2hex(random_bytes(40));
            $token = MobileAccessToken::create([
                'user_id' => $user->id,
                'name' => $data['device_name'] ?? 'Mobile Device',
                'token_hash' => hash('sha256', $secret),
                'abilities' => ['mobile'],
                'expires_at' => now()->addDays($ttlDays),
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to create mobile session token'], 500);
        }

        $audit->log($user->id, 'MOBILE_LOGIN_SUCCESS', 'users', (string) $user->id, [
            'token_id' => $token->id,
            'device_name' => $token->name,
            'ttl_days' => $ttlDays,
        ], $request);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->id.'|'.$secret,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $token = $request->attributes->get('mobile_access_token');
        if ($token instanceof MobileAccessToken) {
            $token->forceFill(['revoked_at' => now()])->save();
            $audit->log($request->user()?->id, 'MOBILE_LOGOUT', 'mobile_access_tokens', (string) $token->id, [], $request);
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $revokedCount = MobileAccessToken::query()
            ->where('user_id', $user?->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $audit->log($user?->id, 'MOBILE_LOGOUT_ALL', 'users', (string) $user?->id, ['revoked_tokens' => $revokedCount], $request);

        return response()->json(['message' => 'All mobile sessions revoked']);
    }

    private function userPayload(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
        ];
    }
}
