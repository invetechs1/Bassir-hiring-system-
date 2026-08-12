@extends('portal.layout')
@section('title', $candidateAssessment->assessment->title)
@section('content')
<section class="card">
    <h2 style="margin-top:0">{{ $candidateAssessment->assessment->title }}</h2>
    @if($candidateAssessment->assessment->description)
        <p class="muted">{{ $candidateAssessment->assessment->description }}</p>
    @endif

    @if(in_array($candidateAssessment->status, ['PENDING', 'IN_PROGRESS']))
        <form method="post" action="{{ route('portal.assessments.submit', $candidateAssessment) }}" enctype="multipart/form-data">
            @csrf
            @if(in_array($candidateAssessment->assessment->type, ['TEST', 'QUESTIONNAIRE']))
                @foreach($candidateAssessment->assessment->questions as $question)
                    <div class="field" style="margin-top:16px">
                        <label>{{ $loop->iteration }}. {{ $question->question_text }}</label>
                        @if($question->question_type === 'MCQ')
                            @foreach($question->options ?? [] as $option)
                                <label style="font-weight:400;display:flex;gap:8px;align-items:center;margin-top:6px">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}" style="width:auto" required>
                                    {{ $option }}
                                </label>
                            @endforeach
                        @else
                            <textarea name="answers[{{ $question->id }}]" rows="3"></textarea>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="field" style="margin-top:16px">
                    <label>Upload document (PDF, JPG, or PNG)</label>
                    <input type="file" name="document" required>
                </div>
            @endif
            <button class="btn btn-dark" style="margin-top:18px">Submit</button>
        </form>
    @else
        <p class="badge">{{ $candidateAssessment->status }}</p>
        <p class="muted">This assessment has already been submitted.</p>
    @endif
</section>
@endsection
