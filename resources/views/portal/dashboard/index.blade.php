@extends('portal.layout')
@section('title', 'My Applications')
@section('content')
<section class="card">
    <h2 style="margin-top:0">My Applications</h2>
    <p class="muted">Track the status of every job you've applied for.</p>
</section>

<section class="grid" style="margin-top:18px">
    @forelse($applications as $application)
        <a class="card" href="{{ route('portal.applications.show', $application) }}">
            <strong>{{ $application->job->title ?? 'Job' }}</strong>
            <div class="muted">{{ $application->job->location ?? '-' }}</div>
            <div style="margin-top:8px"><span class="badge">{{ \App\Http\Controllers\CandidateApplicationController::STAGES[$application->current_stage] ?? $application->current_stage }}</span></div>
        </a>
    @empty
        <div class="card"><p class="muted">You haven't applied to any jobs yet.</p></div>
    @endforelse
</section>
@endsection
