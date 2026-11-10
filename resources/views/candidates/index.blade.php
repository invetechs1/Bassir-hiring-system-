@extends('layouts.app')
@section('title', 'Candidate Database')
@section('content')
@if(session('status'))
    <div class="card" style="border-left:4px solid var(--teal);margin-bottom:14px">{{ session('status') }}</div>
@endif
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <form style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input name="q" placeholder="Search name, title, skill, city or CV text, e.g. civil engineer Revit Riyadh 5 years" value="{{ request('q') }}" style="min-width:340px">
        <select name="mode" style="width:auto" title="How strictly candidates must match your terms">
            <option value="balanced" @selected(($mode ?? 'balanced') === 'balanced')>Best matches</option>
            <option value="all" @selected(($mode ?? '') === 'all')>Match every term</option>
            <option value="any" @selected(($mode ?? '') === 'any')>Match any term</option>
        </select>
        <button class="btn btn-light" type="submit">Search</button>
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
                <td>
                    <a href="{{ route('candidates.show', array_filter(['candidate' => $candidate, 'hl' => ($searching ?? false) ? request('q') : null])) }}"><strong>{{ $candidate->full_name }}</strong><br><span class="muted">{{ $candidate->title }}</span></a>
                    @if($searching ?? false)@include('partials.search-match', ['candidate' => $candidate])@endif
                </td>
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
            <tr><td colspan="7" class="muted" style="text-align:center;padding:24px">{{ ($searching ?? false) ? 'No candidates matched your search terms. Try fewer words, or switch to "Match any term".' : ($archived ? 'No archived candidates.' : 'No candidates found.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $candidates->links() }}</div>
@endsection
