@extends('layouts.app')
@section('title', 'Candidate Database')
@section('content')
@if(session('status'))
    <div class="card" style="border-left:4px solid var(--teal);margin-bottom:14px">{{ session('status') }}</div>
@endif
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <form style="display:flex;gap:8px;align-items:center">
        <input name="q" placeholder="Search candidates" value="{{ request('q') }}">
        @if($archived)<input type="hidden" name="archived" value="1">@endif
    </form>
    <div style="display:flex;gap:8px;align-items:center">
        <a class="btn btn-light" href="{{ route('candidates.index') }}" @style(['background:var(--ink);color:#fff' => !$archived])>Active</a>
        <a class="btn btn-light" href="{{ route('candidates.index', ['archived' => 1]) }}" @style(['background:var(--ink);color:#fff' => $archived])>Archived</a>
        @if(auth()->user()->hasPermission('candidate.write') && !$archived)
        <a class="btn" href="{{ route('candidates.create') }}">Add Candidate</a>
        @endif
    </div>
</div>
<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Name</th><th>Specialization</th><th>City</th><th>Status</th><th>Quality</th><th>Salary</th>@if($archived)<th>Action</th>@else<th>AI Score</th>@endif</tr></thead>
        <tbody>
        @forelse($candidates as $candidate)
            <tr>
                <td><a href="{{ route('candidates.show', $candidate) }}"><strong>{{ $candidate->full_name }}</strong><br><span class="muted">{{ $candidate->title }}</span></a></td>
                <td>{{ $candidate->specialization }}</td>
                <td>{{ $candidate->city }}</td>
                <td><span class="badge">{{ $candidate->status }}</span></td>
                <td>{{ $candidate->quality_score ?? 0 }}%</td>
                <td>{{ $candidate->expected_salary ? number_format((float)$candidate->expected_salary).' SAR' : '-' }}</td>
                @if($archived)
                    <td>
                        @if(auth()->user()->hasPermission('candidate.write'))
                        <form method="post" action="{{ route('candidates.restore', $candidate->id) }}">@csrf<button class="btn btn-light" style="padding:5px 10px">Restore</button></form>
                        @endif
                    </td>
                @else
                    <td>{{ $candidate->scores->last()?->overall ?? '-' }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">{{ $archived ? 'No archived candidates.' : 'No candidates found.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $candidates->links() }}</div>
@endsection
