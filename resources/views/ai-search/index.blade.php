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
    <h2>Generated Queries</h2>
    @foreach($queries as $query)<p><code>{{ $query }}</code></p>@endforeach
</section>
<section class="card" style="margin-top:18px;padding:0">
    <table><thead><tr><th>Source</th><th>Title</th><th>Type</th><th>Compliance</th><th>Import</th></tr></thead><tbody>
    @foreach($results as $result)
        <tr>
            <td>{{ $result['source'] }}</td>
            <td><a href="{{ $result['url'] }}" target="_blank">{{ $result['title'] }}</a><br><span class="muted">{{ $result['snippet'] }}</span></td>
            <td>{{ $result['file_type'] }}</td>
            <td>{{ $result['compliance_status'] }}<br><span class="muted">{{ $result['compliance_note'] }}</span></td>
            <td>
                @if(isset($searchJob))
                    @php($row = $searchJob->results->firstWhere('source_url', $result['url']))
                    @if($row)
                    <form method="post" action="{{ route('ai-search.import-result') }}" style="display:grid;gap:6px;min-width:220px">
                        @csrf
                        <input type="hidden" name="result_id" value="{{ $row->id }}">
                        <input name="specialization" value="{{ request('specialization', 'Unclassified') }}" placeholder="Specialization">
                        <select name="consent_status">
                            <option value="PENDING">PENDING</option>
                            <option value="CONSENTED">CONSENTED</option>
                            <option value="WITHDRAWN">WITHDRAWN</option>
                        </select>
                        <button class="btn btn-dark">Import</button>
                    </form>
                    @else
                    <span class="muted">Unavailable</span>
                    @endif
                @endif
            </td>
        </tr>
    @endforeach
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
    @foreach($history as $job)<p>{{ $job->created_at }} · {{ $job->status }}</p>@endforeach
</section>
@endsection
