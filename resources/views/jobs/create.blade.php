@extends('layouts.app')
@section('title', 'Create Job')
@section('content')
<form method="post" action="{{ route('jobs.store') }}" class="card">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Title</label><input name="title" required></div>
        <div class="field"><label>Main Specialization</label><select name="specialization">
            @foreach($specializations as $item)<option value="{{ $item->name }}">{{ $item->name }} · {{ $item->category }}</option>@endforeach
        </select></div>
        <div class="field"><label>Department</label><input name="department" required></div>
        <div class="field"><label>Company</label><input name="company" required></div>
        <div class="field"><label>Project</label><input name="project"></div>
        <div class="field"><label>Location</label><input name="location" required></div>
        <div class="field"><label>Employment Type</label><select name="employment_type"><option>Full-time</option><option>Part-time</option><option>Contract</option><option>Temporary</option><option>Internship</option></select></div>
        <div class="field"><label>Required Experience</label><input name="required_experience" type="number" value="3"></div>
        <div class="field"><label>Salary Min</label><input name="salary_budget_min" type="number" required></div>
        <div class="field"><label>Salary Max</label><input name="salary_budget_max" type="number" required></div>
        <div class="field"><label>Vacancies</label><input name="vacancies" type="number" value="1"></div>
        <div class="field"><label>Approval</label><select name="approval_status"><option>DRAFT</option><option>PENDING</option><option>APPROVED</option><option>CLOSED</option></select></div>
        <div class="field"><label>Hiring Manager</label><input name="hiring_manager" required></div>
        <div class="field"><label>Required Skills</label><input name="required_skills" placeholder="Revit; BIM 360"></div>
    </div>
    <div class="field" style="margin-top:14px"><label>Description</label><textarea name="description" required></textarea></div>
    <div class="field" style="margin-top:14px"><label>Requirements</label><textarea name="requirements" placeholder="Required education, certifications, tools, project exposure"></textarea></div>
    <div class="field" style="margin-top:14px"><label>Internal Notes</label><textarea name="internal_notes" placeholder="Private recruiter notes"></textarea></div>
    <button class="btn" style="margin-top:18px">Create Job</button>
</form>
@endsection
