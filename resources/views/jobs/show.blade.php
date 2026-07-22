@extends('layouts.app')
@section('title', 'Job Profile')
@section('content')
<section class="card">
    <h2>{{ $job->title }}</h2>
    <p class="muted">{{ $job->company }} · {{ $job->specialization }} · {{ $job->project }} · {{ $job->location }}</p>
    <p>{{ $job->description }}</p>
    @foreach($job->requiredSkills as $skill)<span class="badge">{{ $skill->name }}</span> @endforeach
    @if(auth()->user()->hasPermission('job.match'))
    <div style="display:flex;gap:8px;margin-top:18px;flex-wrap:wrap">
        <form method="post" action="{{ route('jobs.match', $job) }}">@csrf<button class="btn">Run AI Matching</button></form>
        <a class="btn btn-dark" href="{{ route('rankings.job', $job) }}">Open AI Ranking</a>
    </div>
    @endif
</section>
<section class="card" style="margin-top:18px;padding:0">
    <table>
        <thead><tr><th>Candidate</th><th>Overall</th><th>Confidence</th><th>Technical</th><th>Experience</th><th>Education</th><th>Salary Fit</th><th>Location</th><th>Notice</th><th>Availability</th><th>Risk</th><th>Missing Skills</th><th>AI Summary</th><th>Interview Questions</th><th>Salary Strategy</th><th>Recommendation</th></tr></thead>
        <tbody>
        @foreach($job->scores->sortByDesc('overall')->take(20) as $score)
            <tr>
                <td>
                    @if($score->candidate_id)
                        <a href="{{ route('candidates.show', $score->candidate_id) }}">{{ $score->candidate?->full_name }}</a>
                    @else
                        {{ $score->candidate?->full_name ?? '-' }}
                    @endif
                </td>
                <td>{{ $score->overall }}</td>
                <td>{{ $score->confidence ?? ($score->rationale['confidence'] ?? '-') }}</td>
                <td>{{ $score->technical }}</td>
                <td>{{ $score->experience }}</td>
                <td>{{ $score->education }}</td>
                <td>{{ $score->salary_fit }}</td>
                <td>{{ $score->location_fit }}</td>
                <td>{{ $score->notice_period_fit }}</td>
                <td>{{ $score->availability }}</td>
                <td>{{ $score->risk }}</td>
                <td>{{ implode(', ', $score->rationale['missing_skills'] ?? []) ?: '-' }}</td>
                <td>{{ $score->rationale['ai_summary'] ?? '-' }}</td>
                <td>
                    @php($questions = collect($score->interview_questions ?? ($score->rationale['interview_questions'] ?? []))->flatten()->filter()->take(2))
                    @if($questions->isNotEmpty())
                        @foreach($questions as $question)
                            <p style="margin:0 0 6px 0">{{ $question }}</p>
                        @endforeach
                    @else
                        -
                    @endif
                </td>
                <td>{{ $score->rationale['salary_strategy'] ?? '-' }}</td>
                <td><span class="badge">{{ $score->recommendation }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
