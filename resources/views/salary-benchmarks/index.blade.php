@extends('layouts.app')
@section('title', 'Salary Benchmarks')
@section('content')
<div class="grid grid-2">
    <form method="post" action="{{ route('salary-benchmarks.store') }}" class="card">
        @csrf
        <h2>Add Benchmark</h2>
        <div class="grid grid-2">
            <div class="field"><label>Job Title</label><input name="job_title" required></div>
            <div class="field"><label>Location</label><input name="location" value="Riyadh" required></div>
            <div class="field"><label>Min Salary</label><input name="min_salary" type="number" required></div>
            <div class="field"><label>Max Salary</label><input name="max_salary" type="number" required></div>
            <div class="field"><label>Experience Min</label><input name="years_experience_min" type="number" value="0" required></div>
            <div class="field"><label>Experience Max</label><input name="years_experience_max" type="number" value="10" required></div>
        </div>
        <div class="field" style="margin-top:12px"><label>Source</label><input name="source" placeholder="Internal history / market benchmark" required></div>
        <button class="btn" style="margin-top:18px">Save Benchmark</button>
    </form>
    <form method="get" action="{{ route('salary-benchmarks.index') }}" class="card">
        <h2>Salary Estimator</h2>
        <div class="grid grid-2">
            <div class="field"><label>Job Title</label><input name="estimate_job_title" value="{{ request('estimate_job_title') }}"></div>
            <div class="field"><label>Location</label><input name="estimate_location" value="{{ request('estimate_location', 'Riyadh') }}"></div>
            <div class="field"><label>Years Experience</label><input name="estimate_years_experience" type="number" value="{{ request('estimate_years_experience', 5) }}"></div>
            <div class="field"><label>Skills</label><input name="estimate_skills" value="{{ request('estimate_skills', 'Revit; Primavera P6') }}"></div>
            <div class="field"><label>Benchmark Min</label><input name="benchmark_min" type="number" value="{{ request('benchmark_min', 12000) }}"></div>
            <div class="field"><label>Benchmark Max</label><input name="benchmark_max" type="number" value="{{ request('benchmark_max', 22000) }}"></div>
        </div>
        <label style="display:flex;gap:8px;margin-top:12px"><input style="width:auto" name="gcc_experience" type="checkbox" value="1" @checked(request('gcc_experience'))> GCC experience</label>
        <button class="btn btn-dark" style="margin-top:18px">Estimate</button>
        @if($estimate)
            <div class="card" style="margin-top:16px;background:#f8fafc">
                <p><strong>Expected:</strong> {{ number_format($estimate['expected_monthly_salary']) }} SAR</p>
                <p><strong>Range:</strong> {{ number_format($estimate['minimum_fair_salary']) }} - {{ number_format($estimate['maximum_fair_salary']) }} SAR</p>
                <p class="muted">{{ $estimate['negotiation_recommendation'] }}</p>
            </div>
        @endif
    </form>
</div>
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Job</th><th>Location</th><th>Experience</th><th>Range</th><th>Source</th></tr></thead>
        <tbody>
        @foreach($benchmarks as $benchmark)
            <tr>
                <td>{{ $benchmark->job_title }}</td>
                <td>{{ $benchmark->location }}</td>
                <td>{{ $benchmark->years_experience_min }} - {{ $benchmark->years_experience_max }}</td>
                <td>{{ number_format($benchmark->min_salary) }} - {{ number_format($benchmark->max_salary) }} SAR</td>
                <td>{{ $benchmark->source }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
<div style="margin-top:14px">{{ $benchmarks->links() }}</div>
@endsection
