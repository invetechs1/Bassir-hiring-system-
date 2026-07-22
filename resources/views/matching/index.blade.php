@extends('layouts.app')
@section('title', 'AI Matching')
@section('content')
<section class="card">
    <form method="get" action="{{ route('matching.index') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="field" style="min-width:320px">
            <label>Select Job Requisition</label>
            <select name="job_id" required>
                @foreach($jobs as $item)
                    <option value="{{ $item->id }}" @selected($item->id === $selectedJobId)>{{ $item->title }} · {{ $item->location }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn">Load AI Matching</button>
    </form>
    @if($job && auth()->user()->hasPermission('job.match'))
        <form method="post" action="{{ route('jobs.match', $job) }}" style="margin-top:10px">
            @csrf
            <button class="btn btn-dark">Rebuild Scores</button>
        </form>
    @endif
</section>

@if($job)
<section class="card" style="margin-top:18px">
    <h2>{{ $job->title }}</h2>
    <p class="muted">{{ $job->company }} · {{ $job->department }} · {{ $job->location }}</p>
    <p>{{ $job->description }}</p>
    @foreach($job->requiredSkills as $skill)<span class="badge">{{ $skill->name }}</span> @endforeach
</section>

<div class="grid grid-3" style="margin-top:18px">
    @foreach($topThree as $rank => $score)
        <section class="card">
            <h3 style="margin-top:0">Top {{ $rank + 1 }}: {{ $score->candidate?->full_name ?? 'Candidate' }}</h3>
            <p class="muted">{{ $score->candidate?->title }} · {{ $score->candidate?->city }}</p>
            <p><strong>Overall:</strong> {{ $score->overall }} / 100</p>
            <p><strong>Confidence:</strong> {{ $score->confidence ?? ($score->rationale['confidence'] ?? '-') }}%</p>
            <p><strong>Recommendation:</strong> <span class="badge">{{ $score->recommendation }}</span></p>
            <p class="muted">{{ $score->rationale['ai_summary'] ?? 'Run matching to generate AI shortlist summary.' }}</p>
            <p class="muted">{{ $score->rationale['ai_disclaimer'] ?? 'AI assists HR review only.' }}</p>
        </section>
    @endforeach
</div>

<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th>Candidate</th>
                <th>Overall</th>
                <th>Confidence</th>
                <th>Tech</th>
                <th>Experience</th>
                <th>Salary Fit</th>
                <th>Availability</th>
                <th>Risk</th>
                <th>Missing Skills</th>
                <th>AI Questions</th>
                <th>Negotiation Strategy</th>
            </tr>
        </thead>
        <tbody>
        @forelse($scores as $score)
            <tr>
                <td>
                    @if($score->candidate_id)
                        <a href="{{ route('candidates.show', $score->candidate_id) }}"><strong>{{ $score->candidate?->full_name }}</strong></a>
                    @else
                        <strong>{{ $score->candidate?->full_name ?? '-' }}</strong>
                    @endif
                    <br><span class="muted">{{ $score->candidate?->title }}</span>
                </td>
                <td>{{ $score->overall }}</td>
                <td>{{ $score->confidence ?? ($score->rationale['confidence'] ?? '-') }}</td>
                <td>{{ $score->technical }}</td>
                <td>{{ $score->experience }}</td>
                <td>{{ $score->salary_fit }}</td>
                <td>{{ $score->availability }}</td>
                <td>{{ $score->risk }}</td>
                <td>{{ implode(', ', $score->rationale['missing_skills'] ?? []) ?: '-' }}</td>
                <td>
                    @php($questions = collect($score->interview_questions ?? ($score->rationale['interview_questions'] ?? []))->flatten()->filter()->take(2))
                    @if($questions->isNotEmpty())
                        @foreach($questions as $question)
                            <p style="margin:0 0 6px 0">{{ $question }}</p>
                        @endforeach
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
                <td>{{ $score->rationale['salary_strategy'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="muted">No scores yet. Run AI matching from the job profile or this page.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@else
<section class="card" style="margin-top:18px">
    <p class="muted">No jobs found yet. Create a job requisition first, then run AI matching.</p>
</section>
@endif
@endsection
