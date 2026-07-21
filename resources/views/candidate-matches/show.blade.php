@extends('layouts.app')
@section('title', 'Candidate Job Matches')
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap">
        <div>
            <h2 style="margin-top:0">{{ $candidate->full_name }}</h2>
            <p class="muted">{{ $candidate->title }} · {{ $candidate->city }} · Quality {{ $candidate->quality_score }}%</p>
            @foreach($candidate->skills as $skill)<span class="badge">{{ $skill->name }}</span> @endforeach
        </div>
        <form method="post" action="{{ route('candidates.job-matches.rebuild', $candidate) }}">
            @csrf
            <button class="btn btn-dark">Refresh Job Matches</button>
        </form>
    </div>
</section>

<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Job</th><th>Score</th><th>Why It Fits</th><th>Missing</th><th>Apply Signal</th></tr></thead>
        <tbody>
        @forelse($scores as $score)
            <tr>
                <td><a href="{{ route('jobs.show', $score->job) }}"><strong>{{ $score->job?->title }}</strong></a><br><span class="muted">{{ $score->job?->department }} · {{ $score->job?->location }}</span></td>
                <td><strong>{{ $score->overall }}%</strong><br><span class="badge">{{ $score->recommendation }}</span></td>
                <td>{{ $score->rationale['reason_for_ranking'] ?? 'Profile signals match this role.' }}</td>
                <td>{{ implode(', ', $score->rationale['missing_requirements'] ?? []) ?: '-' }}</td>
                <td>{{ $score->overall >= 75 ? 'Should apply' : ($score->overall >= 60 ? 'Recruiter review' : 'Not priority') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No job matches yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
