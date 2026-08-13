@extends('layouts.app')
@section('title', 'Edit Job')
@section('content')
<form method="post" action="{{ route('jobs.update', $job) }}" class="card">
    @csrf
    @method('PUT')
    <div class="grid grid-3">
        <div class="field"><label>Title</label><input name="title" value="{{ old('title', $job->title) }}" required></div>
        <div class="field"><label>Main Specialization</label><select name="specialization">
            @foreach($specializations as $item)<option value="{{ $item->name }}" @selected(old('specialization', $job->specialization) === $item->name)>{{ $item->name }} · {{ $item->category }}</option>@endforeach
        </select></div>
        <div class="field"><label>Department</label><input name="department" value="{{ old('department', $job->department) }}" required></div>
        <div class="field"><label>Company</label><input name="company" value="{{ old('company', $job->company) }}" required></div>
        <div class="field"><label>Project</label><input name="project" value="{{ old('project', $job->project) }}"></div>
        <div class="field"><label>Location</label><input name="location" value="{{ old('location', $job->location) }}" required></div>
        <div class="field"><label>Employment Type</label><select name="employment_type">
            @foreach(['Full-time', 'Part-time', 'Contract', 'Temporary', 'Internship'] as $type)
                <option @selected(old('employment_type', $job->employment_type) === $type)>{{ $type }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Required Experience</label><input name="required_experience" type="number" value="{{ old('required_experience', $job->required_experience) }}"></div>
        <div class="field"><label>Salary Min</label><input name="salary_budget_min" type="number" value="{{ old('salary_budget_min', $job->salary_budget_min) }}" required></div>
        <div class="field"><label>Salary Max</label><input name="salary_budget_max" type="number" value="{{ old('salary_budget_max', $job->salary_budget_max) }}" required></div>
        <div class="field"><label>Vacancies</label><input name="vacancies" type="number" value="{{ old('vacancies', $job->vacancies) }}"></div>
        <div class="field"><label>Approval</label><select name="approval_status">
            @foreach(['DRAFT', 'PENDING', 'APPROVED', 'CLOSED'] as $status)
                <option @selected(old('approval_status', $job->approval_status) === $status)>{{ $status }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Hiring Manager</label><input name="hiring_manager" value="{{ old('hiring_manager', $job->hiring_manager) }}" required></div>
        <div class="field"><label>Required Skills</label><input name="required_skills" value="{{ old('required_skills', $job->requiredSkills->pluck('name')->implode(', ')) }}" placeholder="Revit; BIM 360"></div>
    </div>
    <div class="field" style="margin-top:14px"><label>Description</label><textarea name="description" required>{{ old('description', $job->description) }}</textarea></div>
    <div class="field" style="margin-top:14px"><label>Requirements</label><textarea name="requirements" placeholder="Required education, certifications, tools, project exposure">{{ old('requirements', $job->requirements) }}</textarea></div>
    <div class="field" style="margin-top:14px"><label>Internal Notes</label><textarea name="internal_notes" placeholder="Private recruiter notes">{{ old('internal_notes', $job->internal_notes) }}</textarea></div>
    <button class="btn" style="margin-top:18px">Save Changes</button>
</form>
@endsection
