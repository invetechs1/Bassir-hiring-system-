@extends('layouts.app')
@section('title', 'Assessments')
@section('content')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <h2 style="margin:0">Assessments</h2>
            <p class="muted">Technical tests, questionnaires, document verification, and background checks.</p>
        </div>
        @if(auth()->user()->hasPermission('assessment.manage'))
        <a class="btn" href="{{ route('assessments.create') }}">New Assessment</a>
        @endif
    </div>
</section>

<section class="card" style="margin-top:18px">
    <table>
        <thead><tr><th>Title</th><th>Type</th><th>Job</th><th>Assigned</th><th>Passing Score</th><th></th></tr></thead>
        <tbody>
        @forelse($assessments as $assessment)
            <tr>
                <td>{{ $assessment->title }}</td>
                <td><span class="badge">{{ $assessment->type }}</span></td>
                <td>{{ $assessment->job?->title ?? 'Any job' }}</td>
                <td>{{ $assessment->candidate_assessments_count }}</td>
                <td>{{ $assessment->passing_score ? $assessment->passing_score.'%' : '-' }}</td>
                <td>
                    @if(auth()->user()->hasPermission('assessment.manage'))
                    <form method="post" action="{{ route('assessments.assign', $assessment) }}" style="display:flex;gap:6px">
                        @csrf
                        <input name="candidate_application_id" placeholder="Application ID" style="width:130px" required>
                        <button class="btn btn-light">Assign</button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No assessments yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
