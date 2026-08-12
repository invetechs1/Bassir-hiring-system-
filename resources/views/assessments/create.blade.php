@extends('layouts.app')
@section('title', 'New Assessment')
@section('content')
<section class="card">
    <h2 style="margin-top:0">New Assessment</h2>
    <form method="post" action="{{ route('assessments.store') }}">
        @csrf
        <div class="grid grid-3">
            <div class="field">
                <label>Title</label>
                <input name="title" required>
            </div>
            <div class="field">
                <label>Type</label>
                <select name="type" required>
                    <option value="TEST">Technical Test</option>
                    <option value="QUESTIONNAIRE">Questionnaire</option>
                    <option value="DOCUMENT_VERIFICATION">Document Verification</option>
                    <option value="BACKGROUND_CHECK">Background Check</option>
                </select>
            </div>
            <div class="field">
                <label>Job (optional)</label>
                <select name="job_id">
                    <option value="">Any job</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}">{{ $job->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field" style="margin-top:14px">
            <label>Description</label>
            <textarea name="description" rows="2" placeholder="Shown to the candidate before they start"></textarea>
        </div>
        <div class="field" style="margin-top:14px;max-width:220px">
            <label>Passing score (%)</label>
            <input name="passing_score" type="number" min="0" max="100">
        </div>

        <h3 style="margin-top:24px">Questions (for Test / Questionnaire types)</h3>
        <p class="muted">Leave a row's question text blank to skip it. For MCQ, list choices comma-separated and put the correct choice exactly as typed in "Correct answer".</p>
        @for($i = 0; $i < 8; $i++)
        <div class="grid grid-4" style="margin-top:10px;padding:10px;border:1px solid var(--line);border-radius:8px">
            <div class="field" style="grid-column:1/3">
                <label>Question {{ $i + 1 }}</label>
                <input name="questions[{{ $i }}][question_text]">
            </div>
            <div class="field">
                <label>Type</label>
                <select name="questions[{{ $i }}][question_type]">
                    <option value="MCQ">Multiple choice</option>
                    <option value="TEXT">Free text</option>
                </select>
            </div>
            <div class="field">
                <label>Points</label>
                <input name="questions[{{ $i }}][points]" type="number" min="1" value="1">
            </div>
            <div class="field" style="grid-column:1/3">
                <label>Options (comma-separated, MCQ only)</label>
                <input name="questions[{{ $i }}][options]" placeholder="Option A, Option B, Option C">
            </div>
            <div class="field" style="grid-column:3/5">
                <label>Correct answer (MCQ only)</label>
                <input name="questions[{{ $i }}][correct_answer]">
            </div>
        </div>
        @endfor

        <button class="btn btn-dark" style="margin-top:18px">Create Assessment</button>
    </form>
</section>
@endsection
