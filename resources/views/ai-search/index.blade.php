@extends('layouts.app')
@section('title', 'AI Search and CV Sourcing')
@section('content')
<form method="post" action="{{ route('ai-search.cv-sourcing') }}" class="card">
    @csrf
    <h2>AI CV Sourcing Engine</h2>
    <p class="muted">Uses legal APIs (Google CSE, Bing, SerpAPI, and permitted agency feeds). LinkedIn remains official/manual import only.</p>
    <div class="grid grid-3">
        <div class="field"><label>Job Title</label><input name="job_title" value="{{ old('job_title', 'Senior BIM Engineer') }}"></div>
        <div class="field"><label>Specialization</label><input name="specialization" value="{{ old('specialization', 'BIM Engineers') }}"></div>
        <div class="field"><label>Country</label><input name="country" value="{{ old('country', 'Saudi Arabia') }}"></div>
        <div class="field"><label>City</label><input name="city" value="{{ old('city', 'Riyadh') }}"></div>
        <div class="field"><label>Quantity</label><input name="quantity" type="number" value="{{ old('quantity', 25) }}"></div>
        <div class="field"><label>Skills</label><input name="skills" value="{{ old('skills', 'BIM coordination; QA/QC') }}"></div>
        <div class="field"><label>Software Skills</label><input name="software_skills" value="{{ old('software_skills', 'Revit; Navisworks') }}"></div>
        <div class="field"><label>Languages</label><input name="languages" value="{{ old('languages', 'Arabic; English') }}"></div>
    </div>
    <button class="btn" style="margin-top:18px">Run AI CV Sourcing</button>
</form>
@isset($queries)
<section class="card" style="margin-top:18px">
    <details>
        <summary style="cursor:pointer;font-weight:750;color:var(--ink)">Generated search queries ({{ count($queries) }})</summary>
        <p class="muted" style="margin-top:10px">
            These are the exact queries sent to the configured search providers (Google Custom Search, Bing, SerpAPI),
            shown here for transparency and compliance audit — you don't need to do anything with them.
        </p>
        @foreach($queries as $query)<p><code>{{ $query }}</code></p>@endforeach
    </details>
</section>
<section class="card" style="margin-top:18px;padding:0">
    <div style="padding:20px 20px 0">
        <h2 style="margin:0">Search results ({{ count($results) }})</h2>
        <p class="muted" style="margin-top:6px">
            Review each result, then choose a consent status and click Import to add it as a candidate lead.
            <strong>Nothing is added to your candidate database automatically</strong> — importing is always a manual, per-result decision.
        </p>
    </div>
    <table><thead><tr><th>Source</th><th>Result</th><th>Match</th><th>File Type</th><th>Compliance</th><th style="min-width:230px">Import as candidate</th></tr></thead><tbody>
    @forelse($results as $result)
        @php($quality = $result['match_quality'] ?? 'uncertain')
        <tr>
            <td>{{ $result['source'] }}</td>
            <td>
                @if(!empty($result['candidate_name']))
                    <strong>{{ $result['candidate_name'] }}</strong><br>
                @endif
                <a href="{{ $result['url'] }}" target="_blank">{{ $result['title'] }}</a><br><span class="muted">{{ $result['snippet'] }}</span>
            </td>
            <td>
                @if($quality === 'likely_candidate')
                    <span class="badge" style="background:#dcfce7;color:#166534">Likely candidate</span>
                @elseif($quality === 'likely_job_posting')
                    <span class="badge" style="background:#fee2e2;color:#991b1b">Likely job posting</span>
                @elseif($quality === 'likely_other')
                    <span class="badge" style="background:#e0e7ff;color:#3730a3">Not a CV</span>
                @else
                    <span class="badge" style="background:#f1f5f9;color:#475569">Uncertain</span>
                @endif
            </td>
            <td>{{ $result['file_type'] }}</td>
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
                    @if($row)
                    <form method="post" action="{{ route('ai-search.import-result') }}" style="display:grid;gap:8px">
                        @csrf
                        <input type="hidden" name="result_id" value="{{ $row->id }}">
                        <div class="field">
                            <label style="font-size:11px;text-transform:uppercase;color:#64748b">Specialization</label>
                            <input name="specialization" value="{{ request('specialization', 'Unclassified') }}">
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
        <tr><td colspan="6" class="muted" style="padding:20px">No results were returned for this search. Try broadening the job title, specialization, or removing some skill filters.</td></tr>
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
        <p><a href="{{ route('ai-search.show', $job) }}">{{ $job->created_at }} · {{ $job->status }}</a>
        @if(isset($searchJob) && $searchJob->id === $job->id) <span class="muted">(viewing)</span> @endif
        </p>
    @empty
        <p class="muted">No searches yet.</p>
    @endforelse
</section>
@endsection
