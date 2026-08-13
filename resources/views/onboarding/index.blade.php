@extends('layouts.app')
@section('title', 'Onboarding')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Onboarding</h2>
    <p class="muted">Hired candidates and their onboarding checklist.</p>
</section>

@forelse($records as $record)
<section class="card" style="margin-top:18px">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <strong>{{ $record->candidate->full_name }}</strong>
            <div class="muted">{{ $record->application->job->title ?? '-' }}</div>
        </div>
        <span class="badge">{{ $record->status }}</span>
    </div>
    <table style="margin-top:12px">
        <thead><tr><th>Task</th><th>Type</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($record->tasks as $task)
            <tr>
                <td>{{ $task->title }}</td>
                <td>{{ $task->type }}</td>
                <td><span class="badge">{{ $task->status }}</span></td>
                <td>
                    @if($task->status !== 'COMPLETED')
                    <form method="post" action="{{ route('onboarding.tasks.complete', [$record->id, $task->id]) }}">
                        @csrf
                        <button class="btn btn-light">Mark Complete</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
@empty
<section class="card" style="margin-top:18px"><p class="muted">No onboarding records yet — these are created automatically when a candidate accepts an offer.</p></section>
@endforelse
@endsection
