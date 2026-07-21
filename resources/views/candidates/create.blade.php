@extends('layouts.app')
@section('title', 'Add Candidate')
@section('content')
<form method="post" action="{{ route('candidates.store') }}" class="card">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Full name</label><input name="full_name" required></div>
        <div class="field"><label>Email</label><input name="email" type="email"></div>
        <div class="field"><label>Phone</label><input name="phone"></div>
        <div class="field"><label>LinkedIn URL</label><input name="linkedin_url" type="url"></div>
        <div class="field"><label>Title</label><input name="title" required></div>
        <div class="field"><label>Current Company</label><input name="current_company"></div>
        <div class="field"><label>Specialization</label><select name="specialization" required>
            @foreach($specializations as $item)<option value="{{ $item->name }}">{{ $item->name }} · {{ $item->category }}</option>@endforeach
        </select></div>
        <div class="field"><label>Industry</label><input name="industry" placeholder="Construction / Technology"></div>
        <div class="field"><label>Country</label><input name="country" value="Saudi Arabia"></div>
        <div class="field"><label>City</label><input name="city"></div>
        <div class="field"><label>Nationality</label><input name="nationality"></div>
        <div class="field"><label>Years Experience</label><input name="years_experience" type="number" value="0"></div>
        <div class="field"><label>Expected Salary</label><input name="expected_salary" type="number"></div>
        <div class="field"><label>Availability</label><input name="availability" placeholder="Immediate / 30 days / 60 days"></div>
        <div class="field"><label>Notice Period</label><input name="notice_period" placeholder="Immediate / 30 days"></div>
        <div class="field"><label>Recruiter Rating</label><input name="recruiter_rating" type="number" min="0" max="100"></div>
        <div class="field"><label>Skills</label><input name="skills" placeholder="Revit; Primavera P6"></div>
        <div class="field"><label>Languages</label><input name="languages" placeholder="Arabic; English"></div>
        <div class="field"><label>Consent</label><select name="consent_status"><option>CONSENTED</option><option selected>PENDING</option><option>WITHDRAWN</option></select></div>
    </div>
    <button class="btn" style="margin-top:18px">Create Candidate</button>
</form>
@endsection
