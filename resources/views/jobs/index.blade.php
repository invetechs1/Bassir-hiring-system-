@extends('layouts.app')
@section('title', 'Job Requisitions')
@section('content')
@if(session('status'))
    <div class="card" style="border-left:4px solid var(--teal);margin-bottom:14px">{{ session('status') }}</div>
@endif
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <div style="display:flex;gap:8px;align-items:center">
        <a class="btn btn-light" href="{{ route('jobs.index') }}" @style(['background:var(--ink);color:#fff' => !$archived])>Active</a>
        <a class="btn btn-light" href="{{ route('jobs.index', ['archived' => 1]) }}" @style(['background:var(--ink);color:#fff' => $archived])>Archived</a>
    </div>
    @if(auth()->user()->hasPermission('job.write') && !$archived)
    <a class="btn" href="{{ route('jobs.create') }}">Create Job</a>
    @endif
</div>
<div class="grid grid-3">
@forelse($jobs as $job)
    <div class="card">
        <h2>{{ $job->title }}</h2>
        <p class="muted">{{ $job->department }} · {{ $job->specialization }} · {{ $job->location }}</p>
        <p>{{ \Illuminate\Support\Str::limit($job->description, 130) }}</p>
        <span class="badge">{{ $job->approval_status }}</span>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            @if($archived)
                @if(auth()->user()->hasPermission('job.write'))
                <form method="post" action="{{ route('jobs.restore', $job->id) }}">@csrf<button class="btn btn-light">Restore</button></form>
                @endif
            @else
                <a class="btn btn-light" href="{{ route('jobs.show', $job) }}">Profile</a>
                @if(auth()->user()->hasPermission('job.match'))
                    <a class="btn" href="{{ route('rankings.job', $job) }}">AI Ranking</a>
                @endif
            @endif
        </div>
    </div>
@empty
    <p class="muted">{{ $archived ? 'No archived jobs.' : 'No jobs found.' }}</p>
@endforelse
</div>
<div style="margin-top:14px">{{ $jobs->links() }}</div>
@endsection
