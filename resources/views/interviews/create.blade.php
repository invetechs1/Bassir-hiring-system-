@extends('layouts.app')
@section('title', 'Schedule Interview')
@section('content')
<form method="post" action="{{ route('interviews.store') }}" class="card">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Candidate</label><select name="candidate_id" required>
            @foreach($candidates as $candidate)<option value="{{ $candidate->id }}" @selected($selectedCandidateId === $candidate->id)>{{ $candidate->full_name }} · {{ $candidate->title }}</option>@endforeach
        </select></div>
        <div class="field"><label>Job</label><select name="job_id"><option value="">No job</option>
            @foreach($jobs as $job)<option value="{{ $job->id }}" @selected($selectedJobId === $job->id)>{{ $job->title }} · {{ $job->location }}</option>@endforeach
        </select></div>
        <div class="field"><label>Interview Type</label><select name="interview_type"><option>HR Screening</option><option>Technical</option><option>Final</option><option>Client Interview</option></select></div>
        <div class="field"><label>Channel</label><select name="channel"><option>In Person</option><option>Phone</option><option>Microsoft Teams</option><option>Zoom</option><option>Google Meet</option></select></div>
        <div class="field"><label>Meeting Link</label><input name="meeting_link" type="url" placeholder="https://"></div>
        <div class="field"><label>Starts At</label><input name="starts_at" type="datetime-local" required></div>
        <div class="field"><label>Ends At</label><input name="ends_at" type="datetime-local"></div>
        <div class="field"><label>Status</label><select name="status"><option>SCHEDULED</option><option>COMPLETED</option><option>CANCELLED</option><option>NO_SHOW</option></select></div>
    </div>
    <button class="btn" style="margin-top:18px">Schedule Interview</button>
</form>
@endsection
