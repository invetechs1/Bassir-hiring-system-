@extends('portal.layout')
@section('title', 'My Assessments')
@section('content')
<section class="card">
    <h2 style="margin-top:0">My Assessments</h2>
</section>

<section class="grid" style="margin-top:18px">
    @forelse($assessments as $ca)
        <div class="card">
            <strong>{{ $ca->assessment->title }}</strong>
            <div class="muted">{{ $ca->assessment->job?->title ?? 'General' }}</div>
            <div style="margin-top:8px"><span class="badge">{{ $ca->status }}</span></div>
            @if(in_array($ca->status, ['PENDING', 'IN_PROGRESS']))
                <a class="btn btn-dark" style="margin-top:10px;display:inline-block" href="{{ route('portal.assessments.show', $ca) }}">Start</a>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">No assessments assigned yet.</p></div>
    @endforelse
</section>
@endsection
