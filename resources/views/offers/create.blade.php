@extends('layouts.app')
@section('title', 'Draft Offer')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Draft Offer</h2>
    <p class="muted">{{ $application->candidate->full_name }} · {{ $application->job->title }}</p>
    <form method="post" action="{{ route('offers.store', $application) }}">
        @csrf
        <div class="grid grid-3">
            <div class="field">
                <label>Salary amount</label>
                <input name="salary_amount" type="number" step="0.01" min="0" required>
            </div>
            <div class="field">
                <label>Currency</label>
                <input name="currency" value="SAR">
            </div>
            <div class="field">
                <label>Start date</label>
                <input name="start_date" type="date">
            </div>
        </div>
        <div class="field" style="margin-top:14px">
            <label>Employment type</label>
            <input name="employment_type" value="Full-time">
        </div>
        <div class="field" style="margin-top:14px">
            <label>Terms</label>
            <textarea name="terms" rows="5" placeholder="Benefits, probation period, notice period, etc."></textarea>
        </div>
        <button class="btn btn-dark" style="margin-top:16px">Save Draft (requires approval before sending)</button>
    </form>
</section>
@endsection
