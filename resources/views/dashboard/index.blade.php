@extends('layouts.app')
@section('title', 'Executive Dashboard')
@section('content')
<div class="grid grid-4">
    <div class="card"><div class="muted">Total candidates</div><div class="kpi">{{ $totalCandidates }}</div></div>
    <div class="card"><div class="muted">New (30 days)</div><div class="kpi">{{ $newCandidates }}</div></div>
    <div class="card"><div class="muted">Shortlisted</div><div class="kpi">{{ $shortlisted }}</div></div>
    <div class="card"><div class="muted">Rejected</div><div class="kpi">{{ $rejected }}</div></div>
    <div class="card"><div class="muted">Interviews</div><div class="kpi">{{ $interviews }}</div></div>
    <div class="card"><div class="muted">Open Jobs</div><div class="kpi">{{ $openJobs }}</div></div>
    <div class="card"><div class="muted">AI Searches (30 days)</div><div class="kpi">{{ $aiSearchRuns }}</div></div>
</div>
<div class="grid grid-4" style="margin-top:18px">
    <div class="card"><div class="muted">Avg Time to Shortlist</div><div class="kpi">{{ $timeToHireKpis['avg_time_to_shortlist_hours'] ?? '-' }}</div><div class="muted">hours</div></div>
    <div class="card"><div class="muted">Avg Time to First Interview</div><div class="kpi">{{ $timeToHireKpis['avg_time_to_first_interview_hours'] ?? '-' }}</div><div class="muted">hours</div></div>
    <div class="card"><div class="muted">Avg Time to Hire</div><div class="kpi">{{ $timeToHireKpis['avg_time_to_hire_days'] ?? '-' }}</div><div class="muted">days</div></div>
    <div class="card"><div class="muted">Manual Hours Saved</div><div class="kpi">{{ $timeToHireKpis['manual_hours_saved'] }}</div></div>
    <div class="card"><div class="muted">Candidates Reviewed by AI</div><div class="kpi">{{ $timeToHireKpis['ai_reviewed'] }}</div></div>
    <div class="card"><div class="muted">Shortlisted by AI</div><div class="kpi">{{ $timeToHireKpis['ai_shortlisted'] }}</div></div>
    <div class="card"><div class="muted">Jobs Need Candidates</div><div class="kpi">{{ $timeToHireKpis['jobs_without_enough_candidates'] }}</div></div>
    <div class="card"><div class="muted">Urgent Hiring Positions</div><div class="kpi">{{ $timeToHireKpis['urgent_hiring_positions'] }}</div></div>
</div>
<div class="grid grid-3" style="margin-top:18px">
    <section class="card" style="grid-column:span 2">
        <h2>Recent Candidates</h2>
        <table>
            <thead><tr><th>Name</th><th>Title</th><th>Status</th><th>Score</th></tr></thead>
            <tbody>
            @foreach($recentCandidates as $candidate)
                <tr>
                    <td><a href="{{ route('candidates.show', $candidate) }}">{{ $candidate->full_name }}</a></td>
                    <td>{{ $candidate->title }}</td>
                    <td><span class="badge">{{ $candidate->status }}</span></td>
                    <td>{{ $candidate->scores->last()?->overall ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2>Specializations</h2>
        @foreach($specializations as $row)
            <p><strong>{{ $row->specialization }}</strong> <span class="muted">{{ $row->total }}</span></p>
        @endforeach
        @if(auth()->user()->hasPermission('specialization.manage'))
        <a class="btn btn-light" href="{{ route('specializations.index') }}">Manage Specializations</a>
        @endif
    </section>
</div>
<div class="grid grid-2" style="margin-top:18px">
    <section class="card">
        <h2>Pipeline Breakdown</h2>
        @foreach($statusBreakdown as $row)
            <p><strong>{{ $row->status }}</strong> <span class="muted">{{ $row->total }}</span></p>
        @endforeach
    </section>
    <section class="card">
        <h2>Recent AI Search Runs</h2>
        @forelse($recentSearches as $search)
            <p><strong>#{{ $search->id }}</strong> <span class="muted">{{ $search->created_at }} · {{ $search->status }}</span></p>
        @empty
            <p class="muted">No AI search runs yet.</p>
        @endforelse
    </section>
</div>

<h2 style="margin:24px 0 4px">Recruitment Analytics</h2>
<div class="grid grid-3">
    <section class="card">
        <h3 style="margin-top:0">Source of Hire</h3>
        <p class="muted" style="margin-top:0">Candidates &amp; hires per channel</p>
        <table>
            <thead><tr><th>Source</th><th>Cands</th><th>Hired</th><th>Rate</th></tr></thead>
            <tbody>
            @forelse($sourceOfHire as $row)
                <tr>
                    <td>{{ $row->source_type }}</td>
                    <td>{{ $row->candidates }}</td>
                    <td>{{ $row->hires }}</td>
                    <td>{{ $row->candidates > 0 ? round($row->hires / $row->candidates * 100).'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No source data yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card">
        <h3 style="margin-top:0">Recruiter Productivity</h3>
        <p class="muted" style="margin-top:0">Owned jobs &amp; reviewed applications</p>
        <table>
            <thead><tr><th>Recruiter</th><th>Jobs</th><th>Apps</th></tr></thead>
            <tbody>
            @forelse($recruiters as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['jobs'] }}</td><td>{{ $row['applications'] }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No recruiter activity yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card">
        <h3 style="margin-top:0">Pipeline Stage Distribution</h3>
        <p class="muted" style="margin-top:0">Active applications by stage</p>
        @php($stageMax = max(1, ($stageDistribution->max() ?? 0)))
        @forelse($stageDistribution as $stage => $total)
            <div style="margin-bottom:10px">
                <div style="display:flex;justify-content:space-between;font-size:13px"><span>{{ $stage }}</span><strong>{{ $total }}</strong></div>
                <div style="height:8px;border-radius:6px;background:#e2e8f0;overflow:hidden"><div style="height:100%;width:{{ round($total / $stageMax * 100) }}%;background:var(--teal)"></div></div>
            </div>
        @empty
            <p class="muted">No applications in the pipeline yet.</p>
        @endforelse
    </section>
</div>
@endsection
