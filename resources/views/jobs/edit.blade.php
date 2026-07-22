@extends('layouts.app')
@section('title', 'Edit Job')
@section('content')
<form method="post" action="{{ route('jobs.update', $job) }}" class="card">
    @csrf
    @method('PUT')
    @if($errors->any())
        <div class="card" style="border-left:4px solid #dc2626;margin-bottom:14px">
            <ul style="margin:0;padding-inline-start:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="grid grid-3">
        <div class="field"><label>Title</label><input name="title" value="{{ old('title', $job->title) }}" required></div>
        <div class="field"><label>Main Specialization</label><select name="specialization">
            <option value="">—</option>
            @foreach($specializations as $item)
                <option value="{{ $item->name }}" @selected(old('specialization', $job->specialization) === $item->name)>{{ $item->name }} · {{ $item->category }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Department</label><input name="department" value="{{ old('department', $job->department) }}" required></div>
        <div class="field"><label>Company</label><input name="company" value="{{ old('company', $job->company) }}" required></div>
        <div class="field"><label>Project</label><input name="project" value="{{ old('project', $job->project) }}"></div>
        <div class="field"><label>Location</label><input name="location" value="{{ old('location', $job->location) }}" required></div>
        <div class="field"><label>Employment Type</label><select name="employment_type">
            @foreach(['Full-time','Part-time','Contract','Temporary','Internship'] as $t)
                <option @selected(old('employment_type', $job->employment_type) === $t)>{{ $t }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Required Experience</label><input name="required_experience" type="number" value="{{ old('required_experience', $job->required_experience) }}"></div>
        <div class="field"><label>Salary Min</label><input name="salary_budget_min" type="number" value="{{ old('salary_budget_min', $job->salary_budget_min) }}" required></div>
        <div class="field"><label>Salary Max</label><input name="salary_budget_max" type="number" value="{{ old('salary_budget_max', $job->salary_budget_max) }}" required></div>
        <div class="field"><label>Vacancies</label><input name="vacancies" type="number" value="{{ old('vacancies', $job->vacancies) }}"></div>
        <div class="field"><label>Approval</label><select name="approval_status">
            @foreach(['DRAFT','PENDING','APPROVED','CLOSED'] as $s)
                <option @selected(old('approval_status', $job->approval_status) === $s)>{{ $s }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Hiring Manager</label><input name="hiring_manager" value="{{ old('hiring_manager', $job->hiring_manager) }}" required></div>
        <div class="field"><label>Required Skills</label><input name="required_skills" value="{{ old('required_skills', $job->requiredSkills->pluck('name')->implode('; ')) }}" placeholder="Revit; BIM 360"></div>
    </div>
    <div class="field" style="margin-top:14px"><label>Description</label><textarea name="description" required>{{ old('description', $job->description) }}</textarea></div>
    <div class="field" style="margin-top:14px"><label>Requirements</label><textarea name="requirements">{{ old('requirements', $job->requirements) }}</textarea></div>
    <div class="field" style="margin-top:14px"><label>Internal Notes</label><textarea name="internal_notes">{{ old('internal_notes', $job->internal_notes) }}</textarea></div>
    <div style="display:flex;gap:10px;margin-top:18px">
        <button class="btn">Save Changes</button>
        <a class="btn btn-light" href="{{ route('jobs.show', $job) }}">Cancel</a>
    </div>
</form>
@endsection
