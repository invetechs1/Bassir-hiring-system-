@extends('portal.layout')
@section('title', $company->name.' Careers')
@section('content')
<section class="card">
    <h1 style="margin-top:0">Open Roles at {{ $company->name }}</h1>
    <p class="muted">Browse open positions and apply directly.</p>
</section>

<section class="grid" style="margin-top:18px">
    @forelse($jobs as $job)
        <a class="card" href="{{ route('portal.jobs.show', [$company->slug, $job->public_slug]) }}">
            <strong>{{ $job->title }}</strong>
            <div class="muted">{{ $job->department }} · {{ $job->location }} · {{ $job->employment_type }}</div>
            @if($job->requiredSkills->isNotEmpty())
                <div style="margin-top:8px">
                    @foreach($job->requiredSkills->take(6) as $skill)
                        <span class="badge">{{ $skill->name }}</span>
                    @endforeach
                </div>
            @endif
        </a>
    @empty
        <div class="card"><p class="muted">No open positions right now — check back soon.</p></div>
    @endforelse
</section>

<div style="margin-top:18px">{{ $jobs->links() }}</div>
@endsection
