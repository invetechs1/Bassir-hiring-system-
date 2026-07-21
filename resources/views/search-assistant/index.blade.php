@extends('layouts.app')
@section('title', 'AI Search Assistant')
@section('content')
<section class="card">
    <h2 style="margin-top:0">AI Search Assistant</h2>
    <form method="get" action="{{ route('search-assistant.index') }}" class="grid grid-2" style="align-items:end">
        <div class="field"><label>Natural Language Request</label><input name="q" value="{{ $query }}" placeholder="Find civil engineers with 5 years experience in Riyadh"></div>
        <button class="btn btn-dark">Search Talent</button>
    </form>
</section>

<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Candidate</th><th>Quality</th><th>Skills</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($candidates as $candidate)
            <tr>
                <td><a href="{{ route('candidates.show', $candidate) }}"><strong>{{ $candidate->full_name }}</strong></a><br><span class="muted">{{ $candidate->title }} · {{ $candidate->city }}</span></td>
                <td>{{ $candidate->quality_score }}%</td>
                <td>{{ $candidate->skills->pluck('name')->take(8)->implode(', ') }}</td>
                <td>{{ $candidate->assistant_reason }}</td>
                <td><span class="badge">{{ $candidate->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ $query ? 'No candidates found for this request.' : 'Enter a request to search the talent database.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
