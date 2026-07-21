@extends('layouts.app')
@section('title', 'Job Requisitions')
@section('content')
@if(auth()->user()->hasPermission('job.write'))
<div style="text-align:right;margin-bottom:16px"><a class="btn" href="{{ route('jobs.create') }}">Create Job</a></div>
@endif
<div class="grid grid-3">
@foreach($jobs as $job)
    <div class="card">
        <h2>{{ $job->title }}</h2>
        <p class="muted">{{ $job->department }} · {{ $job->specialization }} · {{ $job->location }}</p>
        <p>{{ \Illuminate\Support\Str::limit($job->description, 130) }}</p>
        <span class="badge">{{ $job->approval_status }}</span>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-light" href="{{ route('jobs.show', $job) }}">Profile</a>
            @if(auth()->user()->hasPermission('job.match'))
                <a class="btn" href="{{ route('rankings.job', $job) }}">AI Ranking</a>
            @endif
        </div>
    </div>
@endforeach
</div>
<div style="margin-top:14px">{{ $jobs->links() }}</div>
@endsection
