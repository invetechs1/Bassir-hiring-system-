<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route($this->postLoginRouteName());
        }
        return view('auth.login');
    }

    public function authenticate(Request $request, AuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $audit->log(null, 'LOGIN_THROTTLED', 'users', null, [
                'username_hash' => hash('sha256', Str::lower($credentials['username'])),
                'wait_seconds' => $seconds,
            ], $request);

            return back()
                ->withErrors(['username' => "Too many login attempts. Try again in {$seconds} seconds."])
                ->onlyInput('username');
        }

        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $credentials['username'], 'password' => $credentials['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            $audit->log(null, 'LOGIN_FAILED', 'users', null, [
                'username_hash' => hash('sha256', Str::lower($credentials['username'])),
                'field' => $field,
            ], $request);
            return back()->withErrors(['username' => 'Invalid credentials'])->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $audit->log(Auth::id(), 'LOGIN', 'users', (string) Auth::id(), [], $request);

        return redirect()->route($this->postLoginRouteName());
    }

    public function logout(Request $request, AuditService $audit): RedirectResponse
    {
        $audit->log(Auth::id(), 'LOGOUT', 'users', (string) Auth::id(), [], $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function postLoginRouteName(): string
    {
        return Auth::user()?->hasPermission('dashboard.view') ? 'dashboard' : 'settings.profile';
    }
}
