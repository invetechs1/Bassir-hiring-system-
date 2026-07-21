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
@endsection
