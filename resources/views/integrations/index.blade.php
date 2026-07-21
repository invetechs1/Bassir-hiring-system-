@extends('layouts.app')
@section('title', 'API Integrations')
@section('content')
<form method="post" action="{{ route('integrations.store') }}" class="card">
    @csrf
    <h2>Encrypted API Key Storage</h2>
    <p class="muted">Store production API credentials in encrypted form for compliant AI sourcing, parsing, and notifications.</p>
    <p class="muted" style="font-size:13px">Auto-sourcing partner connectors (LinkedIn Talent, Indeed) activate once <strong>both</strong> their API token and endpoint are saved and set to ACTIVE.</p>
    <div class="grid grid-3">
        <div class="field"><label>Provider</label><select name="provider">
            @foreach($providers as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="field" style="grid-column:span 2"><label>Secret Value</label><input name="value" type="password" required></div>
        <div class="field"><label>Status</label><select name="status"><option>ACTIVE</option><option>PAUSED</option></select></div>
    </div>
    <button class="btn" style="margin-top:18px">Save Encrypted Key</button>
</form>
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Provider</th><th>Status</th><th>Last Used</th><th>Updated</th></tr></thead>
        <tbody>
        @foreach($keys as $key)
            <tr><td>{{ $providers[$key->provider] ?? $key->provider }}</td><td><span class="badge">{{ $key->status }}</span></td><td>{{ $key->last_used_at ?? '-' }}</td><td>{{ $key->updated_at }}</td></tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
