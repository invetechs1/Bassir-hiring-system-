@extends('layouts.app')
@section('title', 'Candidate Comparison')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Compare Candidates</h2>
    <form method="get" action="{{ route('comparisons.candidates') }}" class="grid grid-2" style="align-items:end">
        <div class="field">
            <label>Select 2-5 Candidates</label>
            <select name="candidate_ids[]" multiple size="8">
                @foreach($candidateOptions as $candidate)
                    <option value="{{ $candidate->id }}" @selected(in_array($candidate->id, request('candidate_ids', [])))>{{ $candidate->full_name }} · {{ $candidate->title }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-dark">Compare</button>
    </form>
</section>

@if($selectedCandidates->count())
@php
    $topScores = $selectedCandidates->mapWithKeys(fn ($candidate) => [$candidate->id => $candidate->scores->sortByDesc('overall')->first()]);
@endphp
@if($selectedCandidates->count() < 2)
    <section class="card" style="margin-top:18px">
        <p class="muted">Select at least two candidates for a side-by-side comparison.</p>
    </section>
@else
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Field</th>@foreach($selectedCandidates as $candidate)<th>{{ $candidate->full_name }}</th>@endforeach</tr></thead>
        <tbody>
            <tr><th>Score</th>@foreach($selectedCandidates as $candidate)<td>{{ $topScores->get($candidate->id)?->overall ?? '-' }}%</td>@endforeach</tr>
            <tr><th>Experience</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->years_experience }} years<br>{{ $candidate->current_company ?: '-' }}</td>@endforeach</tr>
            <tr><th>Skills</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->skills->pluck('name')->implode(', ') ?: '-' }}</td>@endforeach</tr>
            <tr><th>Education</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->education->pluck('degree')->implode(', ') ?: '-' }}</td>@endforeach</tr>
            <tr><th>Certifications</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->certifications->pluck('name')->implode(', ') ?: '-' }}</td>@endforeach</tr>
            <tr><th>Salary</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->expected_salary ? number_format((float) $candidate->expected_salary).' SAR' : '-' }}</td>@endforeach</tr>
            <tr><th>Availability</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->notice_period ?: $candidate->availability ?: '-' }}</td>@endforeach</tr>
            <tr><th>Strengths</th>@foreach($selectedCandidates as $candidate)<td>{{ data_get($topScores->get($candidate->id)?->rationale, 'reason_for_ranking', '-') }}</td>@endforeach</tr>
            <tr><th>Weaknesses</th>@foreach($selectedCandidates as $candidate)<td>{{ implode(', ', $topScores->get($candidate->id)?->risk_indicators ?? []) ?: '-' }}</td>@endforeach</tr>
            <tr><th>AI Recommendation</th>@foreach($selectedCandidates as $candidate)<td>{{ $topScores->get($candidate->id)?->recommendation ?? '-' }}</td>@endforeach</tr>
            <tr><th>Recruiter Notes</th>@foreach($selectedCandidates as $candidate)<td>{{ $candidate->notes->sortByDesc('created_at')->first()?->body ?? '-' }}</td>@endforeach</tr>
        </tbody>
    </table>
</section>
@endif
@endif
@endsection
