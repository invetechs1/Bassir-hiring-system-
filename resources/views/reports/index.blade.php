@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="grid grid-3">
    <section class="card">
        <h2>Candidate Pipeline</h2>
        @foreach($pipelineByStatus as $item)<p><strong>{{ $item->status }}</strong> <span class="muted">{{ $item->total }}</span></p>@endforeach
        <a class="btn" href="{{ route('reports.candidates.csv') }}">Export Pipeline CSV</a>
    </section>
    <section class="card">
        <h2>Hiring Sources</h2>
        @foreach($sources as $item)<p><strong>{{ $item->source_type }}</strong> <span class="muted">{{ $item->total }}</span></p>@endforeach
        <a class="btn btn-dark" href="{{ route('reports.sources.csv') }}">Export Sources CSV</a>
    </section>
    <section class="card">
        <h2>AI Search Success</h2>
        <p><strong>Runs:</strong> {{ $aiSearchStats['runs'] }}</p>
        <p><strong>Completed:</strong> {{ $aiSearchStats['completed'] }}</p>
        <p><strong>Imported Leads:</strong> {{ $aiSearchStats['imported_candidates'] }}</p>
        <a class="btn btn-light" href="{{ route('reports.ai-search-success.csv') }}">Export AI Search CSV</a>
    </section>
</div>
<div class="grid grid-3" style="margin-top:18px">
    <section class="card"><h2>Interview Performance</h2><p class="muted">Technical and HR feedback report.</p><a class="btn" href="{{ route('reports.interviews.csv') }}">Export Interviews CSV</a></section>
    <section class="card"><h2>Salary Benchmark</h2><p class="muted">Benchmark and market range export.</p><a class="btn btn-dark" href="{{ route('reports.salary-benchmarks.csv') }}">Export Salary CSV</a></section>
    <section class="card"><h2>Department Hiring</h2><p class="muted">Department hiring can be derived from jobs and candidate scores in BI tools.</p></section>
</div>
@endsection
