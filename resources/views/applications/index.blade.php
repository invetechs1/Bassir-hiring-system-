@extends('layouts.app')
@section('title', 'Recruitment Pipeline')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Recruitment Pipeline</h2>
    <p class="muted">Track candidate applications by job, stage, reviewer, and decision history.</p>
    <form method="get" action="{{ route('applications.index') }}" class="grid grid-3" style="align-items:end">
        <div class="field">
            <label>Job</label>
            <select name="job_id">
                <option value="">All jobs</option>
                @foreach($jobs as $job)
                    <option value="{{ $job->id }}" @selected((string) request('job_id') === (string) $job->id)>{{ $job->title }} · {{ $job->location }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Stage</label>
            <select name="stage">
                <option value="">All stages</option>
                @foreach($stages as $stage => $label)
                    <option value="{{ $stage }}" @selected(request('stage') === $stage)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn">Filter Pipeline</button>
    </form>
</section>

@if(auth()->user()->hasPermission('candidate.write'))
<section class="card" style="margin-top:18px">
    <h3 style="margin-top:0">Add Candidate To Job</h3>
    <form method="post" action="{{ route('applications.store') }}" class="grid grid-4" style="align-items:end">
        @csrf
        <div class="field">
            <label>Candidate</label>
            <select name="candidate_id" required>
                <option value="">Select candidate</option>
                @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->full_name }} · {{ $candidate->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Job</label>
            <select name="job_id" required>
                <option value="">Select job</option>
                @foreach($jobs as $job)
                    <option value="{{ $job->id }}">{{ $job->title }} · {{ $job->location }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Source</label>
            <input name="source" value="Internal">
        </div>
        <button class="btn btn-dark">Create Application</button>
        <div class="field" style="grid-column:1/-1">
            <label>Notes</label>
            <textarea name="notes" rows="2"></textarea>
        </div>
    </form>
</section>
@endif

<section style="margin-top:18px;display:grid;grid-template-columns:repeat(5,minmax(260px,1fr));gap:14px;overflow:auto;padding-bottom:6px">
    @foreach($stages as $stage => $label)
        @php($stageApplications = $groupedApplications->get($stage, collect()))
        <div class="card" style="min-height:260px;box-shadow:none">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                <h3 style="margin:0;font-size:16px">{{ $label }}</h3>
                <span class="badge">{{ $stageApplications->count() }}</span>
            </div>
            @forelse($stageApplications as $application)
                <article style="margin-top:12px;border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff">
                    <strong>{{ $application->candidate?->full_name ?? 'Candidate' }}</strong>
                    <div class="muted">{{ $application->candidate?->title ?? '-' }}</div>
                    <div style="margin-top:8px">{{ $application->job?->title ?? 'No job' }}</div>
                    <div class="muted">{{ $application->job?->location ?? '-' }}</div>
                    <div style="margin-top:8px">
                        <span class="badge">{{ $application->status }}</span>
                        @php($score = $application->candidate?->scores?->where('job_id', $application->job_id)->sortByDesc('overall')->first())
                        @if($score)
                            <span class="badge">{{ $score->overall }}%</span>
                        @endif
                    </div>
                    @if(auth()->user()->hasPermission('candidate.write'))
                        <form method="post" action="{{ route('applications.stage', $application) }}" style="display:grid;gap:8px;margin-top:10px">
                            @csrf
                            @method('PATCH')
                            <select name="current_stage" required>
                                @foreach($stages as $targetStage => $targetLabel)
                                    <option value="{{ $targetStage }}" @selected($targetStage === $application->current_stage)>{{ $targetLabel }}</option>
                                @endforeach
                            </select>
                            <input name="note" placeholder="Stage note">
                            <input name="rejection_reason" placeholder="Rejection reason if any">
                            <button class="btn btn-light">Update</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="muted">No applications in this stage.</p>
            @endforelse
        </div>
    @endforeach
</section>
@endsection
