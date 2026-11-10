@extends('layouts.app')
@section('title', 'AI Search Assistant')
@section('content')
<section class="card">
    <h2 style="margin-top:0">AI Search Assistant</h2>
    <p class="muted" style="margin-top:0">Describe who you need in plain words. Every result shows which of your requirements it met, which it did not, and where in the CV the match was found.</p>
    <form method="get" action="{{ route('search-assistant.index') }}" class="grid grid-2" style="align-items:end">
        <div class="field"><label>Natural Language Request</label><input name="q" value="{{ $query }}" placeholder="Find civil engineers with Primavera and 5 years experience in Riyadh"></div>
        <div style="display:flex;gap:8px">
            <select name="mode" style="width:auto">
                <option value="balanced" @selected(($mode ?? 'balanced') === 'balanced')>Best matches</option>
                <option value="all" @selected(($mode ?? '') === 'all')>Match every term</option>
                <option value="any" @selected(($mode ?? '') === 'any')>Match any term</option>
            </select>
            <button class="btn btn-dark">Search Talent</button>
        </div>
    </form>
</section>

<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Candidate</th><th>Skills</th><th>Why it matched</th><th>Quality</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($candidates as $candidate)
            <tr>
                <td><a href="{{ route('candidates.show', array_filter(['candidate' => $candidate, 'hl' => $query])) }}"><strong>{{ $candidate->full_name }}</strong></a><br><span class="muted">{{ $candidate->title }} · {{ $candidate->city }}{{ $candidate->years_experience ? ' · '.$candidate->years_experience.'y' : '' }}</span></td>
                <td>{{ $candidate->skills->pluck('name')->take(8)->implode(', ') }}</td>
                <td>@include('partials.search-match', ['candidate' => $candidate])</td>
                <td>{{ $candidate->quality_score }}%</td>
                <td><span class="badge">{{ $candidate->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ $query ? 'No candidates matched this request. Try fewer or simpler words, or switch to "Match any term".' : 'Enter a request to search the talent database.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
