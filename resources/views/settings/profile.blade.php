@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="grid grid-2">
    <section class="card">
        <h2>My Password</h2>
        <p class="muted">Use a strong password. Minimum 10 characters.</p>
        <form method="post" action="{{ route('settings.password.update') }}">
            @csrf
            <div class="field"><label>Current Password</label><input name="current_password" type="password" required></div>
            <div class="field"><label>New Password</label><input name="password" type="password" required></div>
            <div class="field"><label>Confirm New Password</label><input name="password_confirmation" type="password" required></div>
            <button class="btn" style="margin-top:14px">Update Password</button>
        </form>
    </section>
    @if(auth()->user()->isSuperAdmin() && auth()->user()->hasPermission('settings.manage'))
    <section class="card">
        <h2>System Defaults</h2>
        <p class="muted">Shared hosting-safe defaults for locale, currency, and retention.</p>
        <form method="post" action="{{ route('settings.general.update') }}">
            @csrf
            <div class="field"><label>Company Name</label><input name="company_name" value="{{ $settings['company_name'] ?? 'Bassir AI Recruitment System' }}" required></div>
            <div class="field"><label>Default Locale</label><select name="default_locale"><option value="en" @selected(($settings['default_locale'] ?? 'en') === 'en')>English</option><option value="ar" @selected(($settings['default_locale'] ?? 'en') === 'ar')>Arabic</option></select></div>
            <div class="field"><label>Default Currency</label><input name="default_currency" value="{{ $settings['default_currency'] ?? 'SAR' }}" required></div>
            <div class="field"><label>Data Retention Days</label><input name="data_retention_days" type="number" value="{{ $settings['data_retention_days'] ?? 365 }}" required></div>
            <button class="btn btn-dark" style="margin-top:14px">Save Settings</button>
        </form>
    </section>
    @endif
</div>
@endsection
