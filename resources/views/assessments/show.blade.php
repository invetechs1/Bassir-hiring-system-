@extends('layouts.app')
@section('title', 'Assessment Result')
@section('content')
<section class="card">
    <h2 style="margin-top:0">{{ $candidateAssessment->assessment->title }}</h2>
    <p class="muted">{{ $candidateAssessment->candidate->full_name }} · {{ $candidateAssessment->assessment->type }}</p>
    <div class="grid grid-3">
        <div><strong>Status</strong><div class="badge">{{ $candidateAssessment->status }}</div></div>
        <div><strong>Score</strong><div>{{ $candidateAssessment->score ?? '-' }} / {{ $candidateAssessment->max_score ?? '-' }}</div></div>
        <div><strong>Submitted</strong><div>{{ $candidateAssessment->submitted_at?->format('Y-m-d H:i') ?? 'Not yet' }}</div></div>
    </div>

    @if($candidateAssessment->document_path)
        <p style="margin-top:14px"><strong>Uploaded document:</strong> on file (private storage)</p>
    @endif

    @if($candidateAssessment->answers->isNotEmpty())
    <h3 style="margin-top:20px">Answers</h3>
    <table>
        <thead><tr><th>Question</th><th>Answer</th><th>Correct</th><th>Points</th></tr></thead>
        <tbody>
        @foreach($candidateAssessment->answers as $answer)
            <tr>
                <td>{{ $answer->question->question_text }}</td>
                <td>{{ $answer->answer_text }}</td>
                <td>{{ $answer->is_correct === null ? 'Manual review' : ($answer->is_correct ? 'Yes' : 'No') }}</td>
                <td>{{ $answer->points_awarded }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    @if(auth()->user()->hasPermission('assessment.manage'))
    <h3 style="margin-top:20px">Review</h3>
    <form method="post" action="{{ route('assessments.review', $candidateAssessment) }}" class="grid grid-3" style="align-items:end">
        @csrf
        <div class="field">
            <label>Status</label>
            <select name="status" required>
                <option value="SCORED">Scored</option>
                <option value="CLEARED">Cleared</option>
                <option value="FLAGGED">Flagged</option>
            </select>
        </div>
        <div class="field">
            <label>Manual score (%)</label>
            <input name="score" type="number" min="0" max="100">
        </div>
        <div class="field" style="grid-column:1/-1">
            <label>Notes</label>
            <textarea name="notes" rows="2">{{ $candidateAssessment->notes }}</textarea>
        </div>
        <button class="btn btn-dark">Save Review</button>
    </form>
    @endif
</section>
@endsection
