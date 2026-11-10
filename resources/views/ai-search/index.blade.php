@extends('layouts.app')
@section('title', 'AI Search and CV Sourcing')
@section('content')
@php
    $filters = $filters ?? [];
    $val = function (string $key) use ($filters) {
        $stored = $filters[$key] ?? '';

        return old($key, is_array($stored) ? implode('; ', $stored) : $stored);
    };
@endphp
@if(isset($providers) && count($providers) === 0)
<div class="card" style="border-left:4px solid #dc2626;margin-bottom:18px">
    <strong>No search provider is configured, so every search will return zero results.</strong>
    <p class="muted" style="margin:6px 0 0">Add a Google Custom Search key + engine ID, a SerpAPI key, or an agency feed under <a href="{{ route('integrations.index') }}">Integrations</a>. Until then, use the CV Bank to search CVs you already hold.</p>
</div>
@endif
<form method="post" action="{{ route('ai-search.cv-sourcing') }}" class="card">
    @csrf
    <h2>AI CV Sourcing Engine</h2>
    <p class="muted">Finds public CVs and profiles through legal search APIs (Google Custom Search, SerpAPI, permitted agency feeds). LinkedIn remains official/manual import only.
        <strong>Tip:</strong> the job title matters most; add the two or three skills that really decide the hire, not every skill you can think of.</p>
    @error('job_title')<div class="badge" style="background:#fee2e2;color:#991b1b;margin-bottom:10px">{{ $message }}</div>@enderror
    <div class="grid grid-3">
        <div class="field"><label>Job Title</label><input name="job_title" value="{{ $val('job_title') }}" placeholder="e.g. Senior BIM Engineer"></div>
        <div class="field"><label>Specialization</label><input name="specialization" value="{{ $val('specialization') }}" placeholder="e.g. BIM Engineers"></div>
        <div class="field"><label>Country</label><input name="country" value="{{ $val('country') }}" placeholder="e.g. Saudi Arabia"></div>
        <div class="field"><label>City</label><input name="city" value="{{ $val('city') }}" placeholder="e.g. Riyadh"></div>
        <div class="field"><label>Quantity</label><input name="quantity" type="number" min="1" max="100" value="{{ $val('quantity') ?: 25 }}"></div>
        <div class="field"><label>Skills</label><input name="skills" value="{{ $val('skills') }}" placeholder="e.g. BIM coordination; QA/QC"></div>
        <div class="field"><label>Software Skills</label><input name="software_skills" value="{{ $val('software_skills') }}" placeholder="e.g. Revit; Navisworks"></div>
        <div class="field"><label>Languages (used for ranking)</label><input name="languages" value="{{ $val('languages') }}" placeholder="e.g. Arabic; English"></div>
    </div>
    <button class="btn" style="margin-top:18px">Run AI CV Sourcing</button>
</form>
@isset($queries)
<section class="card" style="margin-top:18px">
    <details>
        <summary style="cursor:pointer;font-weight:750;color:var(--ink)">Generated search queries ({{ count($queries) }})</summary>
        <p class="muted" style="margin-top:10px">
            These are the exact queries sent to the configured search providers, from most precise to broadest.
            Searching stops as soon as enough real candidates are found. Shown for transparency and compliance audit.
        </p>
        @foreach($queries as $query)<p><code>{{ $query }}</code></p>@endforeach
    </details>
</section>
<section class="card" style="margin-top:18px;padding:0">
    <div style="padding:20px 20px 0">
        <h2 style="margin:0">Search results ({{ count($results) }})</h2>
        <p class="muted" style="margin-top:6px">
            Results are ordered by how well they match your request. Review each one, then choose a consent status and click Import to add it as a candidate lead.
            <strong>Nothing is added to your candidate database automatically</strong> — importing is always a manual, per-result decision.
        </p>
        @if(($hiddenCount ?? 0) > 0)
            <p class="muted">
                {{ $hiddenCount }} job posting(s), templates or non-CV pages are hidden.
                <a href="{{ route('ai-search.show', ['searchJob' => $searchJob, 'all' => 1]) }}">Show them</a>
            </p>
        @elseif($showAll ?? false)
            <p class="muted"><a href="{{ route('ai-search.show', $searchJob) }}">Hide job postings and non-CV pages</a></p>
        @endif
    </div>
    <table><thead><tr><th>Result</th><th style="min-width:200px">Match with your request</th><th>Type</th><th>Compliance</th><th style="min-width:230px">Import as candidate</th></tr></thead><tbody>
    @forelse($results as $result)
        @php($quality = $result['match_quality'] ?? 'uncertain')
        @php($score = $result['relevance_score'] ?? null)
        <tr>
            <td>
                @if(!empty($result['candidate_name']))
                    <strong>{{ $result['candidate_name'] }}</strong><br>
                @endif
                <a href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer">{{ $result['title'] }}</a><br>
                <span class="muted">{{ $result['snippet'] }}</span><br>
                <span class="muted" style="font-size:11px">{{ $result['source'] }}</span>
            </td>
            <td>
                @if($score !== null)
                    <span class="badge" style="background:{{ $score >= 70 ? '#dcfce7' : ($score >= 40 ? '#fef3c7' : '#f1f5f9') }};color:{{ $score >= 70 ? '#166534' : ($score >= 40 ? '#92400e' : '#475569') }}">{{ $score }}% match</span>
                    <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px">
                        @foreach(($result['matched_terms'] ?? []) as $term)<span class="badge" style="background:#ecfdf5;color:#166534;font-weight:600">{{ $term }}</span>@endforeach
                        @foreach(($result['missing_terms'] ?? []) as $term)<span class="badge" style="background:#f1f5f9;color:#64748b;font-weight:600" title="Not visible in the search snippet; the full CV is checked after import">not seen: {{ $term }}</span>@endforeach
                    </div>
                @else
                    <span class="muted">—</span>
                @endif
                <div style="margin-top:6px">
                @if($quality === 'likely_candidate')
                    <span class="badge" style="background:#dcfce7;color:#166534">Likely candidate</span>
                @elseif($quality === 'likely_job_posting')
                    <span class="badge" style="background:#fee2e2;color:#991b1b">Likely job posting</span>
                @elseif($quality === 'likely_other')
                    <span class="badge" style="background:#e0e7ff;color:#3730a3">Not a CV</span>
                @else
                    <span class="badge" style="background:#f1f5f9;color:#475569">Uncertain</span>
                @endif
                </div>
            </td>
            <td>{{ strtoupper($result['file_type']) }}</td>
            <td>
                @if($result['compliance_status'] === 'allowed')
                    <span class="badge">Allowed</span>
                @else
                    <span class="badge" style="background:#fef3c7;color:#92400e">Manual review required</span>
                @endif
                <div class="muted" style="margin-top:4px;font-size:12px">{{ $result['compliance_note'] }}</div>
            </td>
            <td>
                @if(isset($searchJob))
                    @php($row = $searchJob->results->firstWhere('source_url', $result['url']))
                    @if($row && $row->candidate_id)
                        <a class="btn btn-light" href="{{ route('candidates.show', $row->candidate_id) }}">Already imported — open</a>
                    @elseif($row)
                    <form method="post" action="{{ route('ai-search.import-result') }}" style="display:grid;gap:8px">
                        @csrf
                        <input type="hidden" name="result_id" value="{{ $row->id }}">
                        <div class="field">
                            <label style="font-size:11px;text-transform:uppercase;color:#64748b">Specialization</label>
                            <input name="specialization" value="" placeholder="Auto-detect{{ !empty($filters['specialization']) ? ' (searched: '.$filters['specialization'].')' : '' }}">
                        </div>
                        <div class="field">
                            <label style="font-size:11px;text-transform:uppercase;color:#64748b">Consent status</label>
                            <select name="consent_status">
                                <option value="PENDING">Pending — not yet contacted</option>
                                <option value="CONSENTED">Consented — candidate agreed to be contacted</option>
                                <option value="WITHDRAWN">Withdrawn</option>
                            </select>
                        </div>
                        <button class="btn btn-dark">Import as Candidate</button>
                    </form>
                    @else
                    <span class="muted">Unavailable</span>
                    @endif
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted" style="padding:20px">
            No matching CVs were found for this search.
            @if(isset($providers) && count($providers) === 0)
                No search provider is configured (see the notice above).
            @else
                Try a broader job title, remove the city, or keep only the two or three most important skills. Public CV files are rare on the open web, so the CV Bank is usually the better source for niche roles.
            @endif
        </td></tr>
    @endforelse
    </tbody></table>
</section>
@endisset
<section class="card" style="margin-top:18px">
    <h2>LinkedIn Manual Import (Compliant)</h2>
    <p class="muted">Use this when HR has a lawful LinkedIn URL and consent basis. The system stores URL metadata only and does not scrape protected profiles.</p>
    <form method="post" action="{{ route('ai-search.import-linkedin-manual') }}" class="grid grid-3">
        @csrf
        <div class="field"><label>LinkedIn URL</label><input name="linkedin_url" type="url" required></div>
        <div class="field"><label>Full Name</label><input name="full_name" required></div>
        <div class="field"><label>Title</label><input name="title" required></div>
        <div class="field"><label>Specialization</label><input name="specialization" value="Project Managers" required></div>
        <div class="field"><label>Country</label><input name="country" value="Saudi Arabia"></div>
        <div class="field"><label>City</label><input name="city" value="Riyadh"></div>
        <div class="field"><label>Consent Status</label><select name="consent_status"><option>PENDING</option><option>CONSENTED</option><option>WITHDRAWN</option></select></div>
        <div><button class="btn" style="margin-top:28px">Import LinkedIn Manually</button></div>
    </form>
</section>
<section class="card" style="margin-top:18px">
    <h2>Search History</h2>
    @forelse($history as $job)
        <p><a href="{{ route('ai-search.show', $job) }}">{{ $job->created_at }} · {{ $job->filters['job_title'] ?? ($job->filters['specialization'] ?? 'Search') }} · {{ $job->status }}</a>
        @if(isset($searchJob) && $searchJob->id === $job->id) <span class="muted">(viewing)</span> @endif
        </p>
    @empty
        <p class="muted">No searches yet.</p>
    @endforelse
</section>
@endsection
