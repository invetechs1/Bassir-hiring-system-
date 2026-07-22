<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function profile(): View
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['company_name', 'default_locale', 'default_currency', 'data_retention_days'])
            ->get()
            ->mapWithKeys(fn ($item) => [$item->key => $item->value['value'] ?? null]);

        return view('settings.profile', ['settings' => $settings]);
    }

    public function updateGeneral(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:180'],
            'default_locale' => ['required', 'in:en,ar'],
            'default_currency' => ['required', 'string', 'max:20'],
            'data_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
        }

        $audit->log(Auth::id(), 'SYSTEM_SETTINGS_UPDATE', 'system_settings', null, $data, $request);
        return back()->with('status', 'System settings updated');
    }

    public function updatePassword(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'max:120', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);
        $audit->log(Auth::id(), 'PASSWORD_CHANGE', 'users', (string) $user->id, [], $request);

        return back()->with('status', 'Password updated successfully');
    }
}
