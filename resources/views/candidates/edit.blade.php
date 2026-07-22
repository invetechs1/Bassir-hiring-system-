@extends('layouts.app')
@section('title', 'Edit Candidate')
@section('content')
<form method="post" action="{{ route('candidates.update', $candidate) }}" class="card">
    @csrf
    @method('PUT')
    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626;margin-bottom:14px">
            <ul style="margin:0;padding-inline-start:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="grid grid-3">
        <div class="field"><label>Full name</label><input name="full_name" value="{{ old('full_name', $candidate->full_name) }}" required></div>
        <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $candidate->email) }}"></div>
        <div class="field"><label>Phone</label><input name="phone" value="{{ old('phone', $candidate->phone) }}"></div>
        <div class="field"><label>LinkedIn URL</label><input name="linkedin_url" type="url" value="{{ old('linkedin_url', $candidate->linkedin_url) }}"></div>
        <div class="field"><label>Title</label><input name="title" value="{{ old('title', $candidate->title) }}" required></div>
        <div class="field"><label>Current Company</label><input name="current_company" value="{{ old('current_company', $candidate->current_company) }}"></div>
        <div class="field"><label>Specialization</label><select name="specialization" required>
            @foreach($specializations as $item)
                <option value="{{ $item->name }}" @selected(old('specialization', $candidate->specialization) === $item->name)>{{ $item->name }} · {{ $item->category }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Industry</label><input name="industry" value="{{ old('industry', $candidate->industry) }}"></div>
        <div class="field"><label>Country</label><input name="country" value="{{ old('country', $candidate->country) }}"></div>
        <div class="field"><label>City</label><input name="city" value="{{ old('city', $candidate->city) }}"></div>
        <div class="field"><label>Nationality</label><input name="nationality" value="{{ old('nationality', $candidate->nationality) }}"></div>
        <div class="field"><label>Years Experience</label><input name="years_experience" type="number" value="{{ old('years_experience', $candidate->years_experience) }}"></div>
        <div class="field"><label>Expected Salary</label><input name="expected_salary" type="number" value="{{ old('expected_salary', $candidate->expected_salary) }}"></div>
        <div class="field"><label>Current Salary</label><input name="current_salary" type="number" value="{{ old('current_salary', $candidate->current_salary) }}"></div>
        <div class="field"><label>Availability</label><input name="availability" value="{{ old('availability', $candidate->availability) }}"></div>
        <div class="field"><label>Notice Period</label><input name="notice_period" value="{{ old('notice_period', $candidate->notice_period) }}"></div>
        <div class="field"><label>Recruiter Rating</label><input name="recruiter_rating" type="number" min="0" max="100" value="{{ old('recruiter_rating', $candidate->recruiter_rating) }}"></div>
        <div class="field"><label>Skills</label><input name="skills" value="{{ old('skills', $candidate->skills->pluck('name')->implode('; ')) }}" placeholder="Revit; Primavera P6"></div>
        <div class="field"><label>Languages</label><input name="languages" value="{{ old('languages', $candidate->languages->pluck('name')->implode('; ')) }}" placeholder="Arabic; English"></div>
        <div class="field"><label>Consent</label><select name="consent_status">
            @foreach(['CONSENTED','PENDING','WITHDRAWN'] as $c)
                <option @selected(old('consent_status', $candidate->consent_status) === $c)>{{ $c }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Status</label><select name="status">
            @foreach(['NEW','REVIEWED','SHORTLISTED','INTERVIEW','OFFER','HIRED','REJECTED','BLACKLISTED'] as $s)
                <option @selected(old('status', $candidate->status) === $s)>{{ $s }}</option>
            @endforeach
        </select></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
        <button class="btn">Save Changes</button>
        <a class="btn btn-light" href="{{ route('candidates.show', $candidate) }}">Cancel</a>
    </div>
</form>
@endsection
