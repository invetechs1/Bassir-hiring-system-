@extends('layouts.app')
@section('title', 'AI Candidate Ranking')
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap">
        <div>
            <h2 style="margin-top:0">{{ $job->title }}</h2>
            <p class="muted">{{ $job->department }} · {{ $job->location }} · {{ $job->required_experience }}+ years</p>
            @foreach($job->requiredSkills as $skill)<span class="badge">{{ $skill->name }}</span> @endforeach
        </div>
        <form method="post" action="{{ route('rankings.job.rebuild', $job) }}">
            @csrf
            <button class="btn btn-dark">Rebuild AI Ranking</button>
        </form>
    </div>
</section>

<div class="grid grid-3" style="margin-top:18px">
    <section class="card"><div class="muted">Matches 80%+</div><div class="kpi">{{ $grouped['80_plus']->count() }}</div></section>
    <section class="card"><div class="muted">Matches 60-79%</div><div class="kpi">{{ $grouped['60_79']->count() }}</div></section>
    <section class="card"><div class="muted">Weak Matches</div><div class="kpi">{{ $grouped['weak']->count() }}</div></section>
</div>

<section class="card" style="margin-top:18px">
    <form method="get" action="{{ route('rankings.job', $job) }}" class="grid grid-4" style="align-items:end">
        <div class="field"><label>Score Above %</label><input name="score_min" type="number" min="0" max="100" value="{{ request('score_min') }}"></div>
        <div class="field"><label>Skill</label><input name="skill" value="{{ request('skill') }}"></div>
        <div class="field"><label>Years Min</label><input name="years_min" type="number" min="0" value="{{ request('years_min') }}"></div>
        <div class="field"><label>City</label><input name="city" value="{{ request('city') }}"></div>
        <div class="field"><label>Salary Min</label><input name="salary_min" type="number" min="0" value="{{ request('salary_min') }}"></div>
        <div class="field"><label>Salary Max</label><input name="salary_max" type="number" min="0" value="{{ request('salary_max') }}"></div>
        <div class="field"><label>Availability</label><input name="availability" value="{{ request('availability') }}"></div>
        <div class="field"><label>Education</label><input name="education" value="{{ request('education') }}"></div>
        <div class="field"><label>Language</label><input name="language" value="{{ request('language') }}"></div>
        <div class="field"><label>Nationality</label><input name="nationality" value="{{ request('nationality') }}"></div>
        <div class="field"><label>Status</label><select name="status"><option value="">Any</option>@foreach(['NEW','REVIEWED','SHORTLISTED','INTERVIEW','OFFER','HIRED','REJECTED','BLACKLISTED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <button class="btn">Filter</button>
    </form>
</section>

<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead>
        <tr>
            <th>Candidate</th><th>CV</th><th>AI Score</th><th>Skills</th><th>Exp</th><th>Edu</th><th>Salary</th><th>Location</th><th>Notice</th><th>Risk</th><th>Recommendation</th><th>Decision</th>
        </tr>
        </thead>
        <tbody>
        @forelse($scores as $score)
            @php($candidate = $score->candidate)
            <tr>
                <td>
                    <a href="{{ route('candidates.show', $candidate) }}"><strong>{{ $candidate?->full_name }}</strong></a><br>
                    <span class="muted">{{ $candidate?->title }} · {{ $candidate?->city }}</span><br>
                    <span class="muted">{{ $score->rationale['reason_for_ranking'] ?? '-' }}</span>
                </td>
                <td>
                    @php($document = $candidate?->documents?->first())
                    @if($document)
                        <a class="btn btn-light" href="{{ route('candidates.documents.download', [$candidate, $document]) }}">Download</a>
                    @else
                        <span class="muted">No CV</span>
                    @endif
                </td>
                <td><strong>{{ $score->overall }}%</strong><br><span class="badge">{{ $score->ranking_band }}</span></td>
                <td>{{ $score->technical }}%</td>
                <td>{{ $score->experience }}%</td>
                <td>{{ $score->education }}%</td>
                <td>{{ $score->salary_fit }}%</td>
                <td>{{ $score->location_fit }}%</td>
                <td>{{ $score->notice_period_fit }}%</td>
                <td>
                    @forelse(array_slice($score->risk_indicators ?? [], 0, 3) as $flag)
                        <div class="muted">{{ $flag }}</div>
                    @empty
                        <span class="badge">Low</span>
                    @endforelse
                </td>
                <td><span class="badge">{{ $score->recommendation }}</span></td>
                <td>
                    <form method="post" action="{{ route('rankings.job.decision', [$job, $candidate]) }}" style="display:grid;gap:7px;min-width:190px">
                        @csrf
                        <select name="decision">
                            <option value="SHORTLIST">Move to shortlist</option>
                            <option value="SCHEDULE_INTERVIEW">Schedule interview</option>
                            <option value="KEEP_REVIEW">Needs review</option>
                            <option value="REJECT">Reject</option>
                        </select>
                        <select name="ai_feedback">
                            <option value="">AI feedback</option>
                            <option value="CORRECT">Correct</option>
                            <option value="WRONG">Wrong</option>
                            <option value="NEEDS_REVIEW">Needs review</option>
                        </select>
                        <input name="note" placeholder="Recruiter note">
                        <button class="btn">Save</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="12" class="muted">No candidates ranked yet. Rebuild AI Ranking for this job.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div style="margin-top:14px">{{ $scores->links() }}</div>
@endsection
