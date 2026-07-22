@extends('layouts.app')
@section('title', 'Auto Sourcing')
@section('content')

@if(session('status'))
    <div class="card" style="border-left:4px solid var(--teal);margin-bottom:16px">{{ session('status') }}</div>
@endif

<section class="card" style="margin-bottom:16px">
    <h2 style="margin-top:0">Automated Web Sourcing</h2>
    <p class="muted" style="margin-top:4px">
        Saved searches run automatically across official search APIs and partner connectors, then import matching
        candidates as leads (consent pending). Compliant by design — no scraping, no automatic outreach.
        LinkedIn public-web results are flagged for manual review; LinkedIn data is imported automatically only
        through the official partner API connector.
    </p>

    <div class="grid grid-2" style="margin-top:14px;gap:12px">
        <div>
            <div class="muted" style="font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.05em;margin-bottom:8px">Search API providers</div>
            @foreach($webProviders as $p)
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
                    <span>{{ $p['label'] }}</span>
                    <span class="badge" @style(['background:#fee2e2;color:#991b1b' => !$p['configured']])>{{ $p['configured'] ? 'Connected' : 'Add API key' }}</span>
                </div>
            @endforeach
        </div>
        <div>
            <div class="muted" style="font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.05em;margin-bottom:8px">Official platform connectors</div>
            @foreach($connectors as $c)
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
                    <span>{{ $c['label'] }}</span>
                    <span class="badge" @style(['background:#fef3c7;color:#92400e' => !$c['configured']])>{{ $c['configured'] ? 'Connected' : 'Connect account' }}</span>
                </div>
            @endforeach
            <p class="muted" style="font-size:12px;margin-top:8px">Connectors activate automatically once their API token is saved under Integrations.</p>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">New saved search</h3>
    <form method="post" action="{{ route('auto-sourcing.store') }}">
        @csrf
        <div class="grid grid-3" style="align-items:end">
            <div class="field"><label>Search name</label><input name="name" required placeholder="Senior BIM Engineers · Riyadh"></div>
            <div class="field"><label>Job title</label><input name="job_title" placeholder="Senior BIM Engineer"></div>
            <div class="field"><label>Specialization</label><input name="specialization" placeholder="BIM Engineers"></div>
            <div class="field"><label>Country</label><input name="country" placeholder="Saudi Arabia"></div>
            <div class="field"><label>City</label><input name="city" placeholder="Riyadh"></div>
            <div class="field"><label>Quantity (max 100)</label><input type="number" name="quantity" value="25" min="1" max="100"></div>
            <div class="field"><label>Skills (comma separated)</label><input name="skills" placeholder="Revit, Navisworks, BIM 360"></div>
            <div class="field"><label>Software skills</label><input name="software_skills" placeholder="Clash Detection, QA/QC"></div>
            <div class="field"><label>Languages</label><input name="languages" placeholder="Arabic, English"></div>
            <div class="field"><label>Frequency</label><select name="frequency"><option value="daily">Daily (automatic)</option><option value="weekly">Weekly (automatic)</option><option value="manual">Manual only</option></select></div>
            <div class="field"><label>Consent basis on import</label><select name="default_consent_status"><option value="PENDING">Pending — do not contact</option><option value="CONSENTED">Consented (only if you hold consent)</option></select></div>
            <div class="field">
                <label>Options</label>
                <label style="font-weight:400;font-size:13px"><input type="checkbox" name="download_cvs" value="1" checked style="width:auto"> Download &amp; parse public CV files</label>
                <label style="font-weight:400;font-size:13px"><input type="checkbox" name="auto_import" value="1" checked style="width:auto"> Auto-create candidate leads</label>
            </div>
        </div>
        <button class="btn" style="margin-top:12px">Save search</button>
    </form>
</section>

<section class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">Saved searches</h3>
    @forelse($searches as $search)
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;border-top:1px solid var(--line);padding:12px 0">
            <div>
                <strong>{{ $search->name }}</strong>
                <div class="muted" style="font-size:13px">
                    {{ $search->job_title ?: $search->specialization ?: 'Any role' }}
                    · {{ trim(($search->city ? $search->city.', ' : '').$search->country) ?: 'Any location' }}
                    · {{ ucfirst($search->frequency) }}
                    @if($search->last_run_at) · last run {{ $search->last_run_at->diffForHumans() }} ({{ $search->last_import_count }} imported) @else · never run @endif
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <form method="post" action="{{ route('auto-sourcing.run', $search) }}">
                    @csrf
                    <button class="btn">Run now</button>
                </form>
                <form method="post" action="{{ route('auto-sourcing.destroy', $search) }}" onsubmit="return confirm('Delete this saved search?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-light">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <p class="muted">No saved searches yet. Create one above to start automated sourcing.</p>
    @endforelse
</section>

<section class="card">
    <h3 style="margin-top:0">Recent runs</h3>
    <table>
        <thead><tr><th>Search</th><th>Status</th><th>Results</th><th>Created</th><th>Linked</th><th>CVs</th><th>Manual</th><th>When</th></tr></thead>
        <tbody>
        @forelse($runs as $run)
            <tr>
                <td>{{ $run->search?->name ?? '—' }}</td>
                <td><span class="badge" @style(['background:#fee2e2;color:#991b1b' => $run->status === 'FAILED'])>{{ $run->status }}</span></td>
                <td>{{ $run->results_found }}</td>
                <td>{{ $run->candidates_created }}</td>
                <td>{{ $run->candidates_linked }}</td>
                <td>{{ $run->cvs_downloaded }}</td>
                <td>{{ $run->flagged_manual }}</td>
                <td class="muted">{{ $run->created_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No runs yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

@endsection
