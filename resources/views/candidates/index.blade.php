@extends('layouts.app')
@section('title', 'Candidate Database')
@section('content')
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
    <form><input name="q" placeholder="Search candidates" value="{{ request('q') }}"></form>
    @if(auth()->user()->hasPermission('candidate.write'))
    <a class="btn" href="{{ route('candidates.create') }}">Add Candidate</a>
    @endif
</div>
<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Name</th><th>Specialization</th><th>City</th><th>Status</th><th>Quality</th><th>Salary</th><th>AI Score</th></tr></thead>
        <tbody>
        @foreach($candidates as $candidate)
            <tr>
                <td><a href="{{ route('candidates.show', $candidate) }}"><strong>{{ $candidate->full_name }}</strong><br><span class="muted">{{ $candidate->title }}</span></a></td>
                <td>{{ $candidate->specialization }}</td>
                <td>{{ $candidate->city }}</td>
                <td><span class="badge">{{ $candidate->status }}</span></td>
                <td>{{ $candidate->quality_score ?? 0 }}%</td>
                <td>{{ $candidate->expected_salary ? number_format((float)$candidate->expected_salary).' SAR' : '-' }}</td>
                <td>{{ $candidate->scores->last()?->overall ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $candidates->links() }}</div>
@endsection
