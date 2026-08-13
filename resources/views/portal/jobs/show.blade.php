@extends('portal.layout')
@section('title', $job->title)
@section('content')
<section class="card">
    <h1 style="margin-top:0">{{ $job->title }}</h1>
    <p class="muted">{{ $job->department }} · {{ $job->location }} · {{ $job->employment_type }}</p>
    @if($job->requiredSkills->isNotEmpty())
        <div style="margin:10px 0">
            @foreach($job->requiredSkills as $skill)
                <span class="badge">{{ $skill->name }}</span>
            @endforeach
        </div>
    @endif
    <h3>Description</h3>
    <p>{{ $job->description }}</p>
    @if($job->requirements)
        <h3>Requirements</h3>
        <p>{{ $job->requirements }}</p>
    @endif
</section>

<section class="card" style="margin-top:18px">
    @auth('candidate')
        <h3 style="margin-top:0">Apply for this role</h3>
        <form method="post" action="{{ route('portal.jobs.apply', [$company->slug, $job->public_slug]) }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>CV (optional — PDF, DOC, or DOCX)</label>
                <input type="file" name="cv">
            </div>
            <button class="btn btn-dark" style="margin-top:14px">Submit Application</button>
        </form>
    @else
        <p>Please <a href="{{ route('portal.login', $company->slug) }}"><strong>log in</strong></a> or <a href="{{ route('portal.register', $company->slug) }}"><strong>register</strong></a> to apply.</p>
    @endauth
</section>
@endsection
