@extends('portal.layout')
@section('title', 'Application Status')
@section('content')
<section class="card">
    <h2 style="margin-top:0">{{ $application->job->title ?? 'Job' }}</h2>
    <p class="muted">{{ $application->job->location ?? '-' }}</p>
    <div class="badge">{{ \App\Http\Controllers\CandidateApplicationController::STAGES[$application->current_stage] ?? $application->current_stage }}</div>
</section>

@if($application->offers->isNotEmpty())
<section class="card" style="margin-top:18px">
    <h3 style="margin-top:0">Offer</h3>
    @foreach($application->offers as $offer)
        <p>Status: <span class="badge">{{ $offer->status }}</span></p>
        @if($offer->status === 'SENT')
            <a class="btn btn-dark" href="{{ route('portal.offers.show', $offer) }}">View & Respond to Offer</a>
        @endif
    @endforeach
</section>
@endif

@if($application->assessments->isNotEmpty())
<section class="card" style="margin-top:18px">
    <h3 style="margin-top:0">Assessments</h3>
    @foreach($application->assessments as $ca)
        <p>{{ $ca->assessment->title }} — <span class="badge">{{ $ca->status }}</span>
            @if(in_array($ca->status, ['PENDING', 'IN_PROGRESS']))
                <a class="btn btn-light" href="{{ route('portal.assessments.show', $ca) }}">Start</a>
            @endif
        </p>
    @endforeach
</section>
@endif

<section class="card" style="margin-top:18px">
    <h3 style="margin-top:0">Timeline</h3>
    <table>
        <thead><tr><th>Date</th><th>Stage</th><th>Note</th></tr></thead>
        <tbody>
        @foreach($application->stageHistories->sortByDesc('created_at') as $history)
            <tr>
                <td>{{ $history->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ \App\Http\Controllers\CandidateApplicationController::STAGES[$history->to_stage] ?? $history->to_stage }}</td>
                <td>{{ $history->note }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@endsection
