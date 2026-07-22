@extends('layouts.app')
@section('title', 'Interview Management')
@section('content')
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
    <div>
        <h2 style="margin:0">Interviews</h2>
        <p class="muted">Schedule interviews and collect technical/HR feedback.</p>
    </div>
    @if(auth()->user()->hasPermission('interview.write'))
    <a class="btn" href="{{ route('interviews.create') }}">Schedule Interview</a>
    @endif
</div>
<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Candidate</th><th>Job</th><th>Date</th><th>Channel</th><th>Status</th><th>Feedback</th></tr></thead>
        <tbody>
        @foreach($interviews as $interview)
            <tr>
                <td>{{ $interview->candidate?->full_name }}</td>
                <td>{{ $interview->job?->title ?? '-' }}</td>
                <td>{{ $interview->starts_at?->format('Y-m-d H:i') }}</td>
                <td>{{ $interview->channel }}</td>
                <td><span class="badge">{{ $interview->status }}</span></td>
                <td>
                    @if(auth()->user()->hasPermission('interview.feedback'))
                    <form method="post" action="{{ route('interviews.feedback', $interview) }}" style="display:grid;gap:8px;min-width:260px">
                        @csrf
                        <input name="technical_score" type="number" min="0" max="100" placeholder="Technical score">
                        <input name="hr_score" type="number" min="0" max="100" placeholder="HR score">
                        <input name="recommendation" placeholder="Recommendation">
                        <textarea name="comments" placeholder="Feedback notes"></textarea>
                        <button class="btn">Save Feedback</button>
                    </form>
                    @else
                        <span class="muted">No edit permission</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $interviews->links() }}</div>
@endsection
