@extends('layouts.app')
@section('title', 'Talent Pools')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Talent Pools</h2>
    <form method="post" action="{{ route('talent-pools.store') }}" class="grid grid-4" style="align-items:end">
        @csrf
        <div class="field"><label>Name</label><input name="name" required></div>
        <div class="field"><label>Category</label><select name="category">@foreach($categories as $category)<option>{{ $category }}</option>@endforeach</select></div>
        <div class="field"><label>Description</label><input name="description"></div>
        <button class="btn">Create Pool</button>
    </form>
</section>

<div class="grid grid-3" style="margin-top:18px">
@foreach($pools as $pool)
    <section class="card">
        <h3 style="margin-top:0">{{ $pool->name }}</h3>
        <p class="muted">{{ $pool->category }} · {{ $pool->candidates_count }} candidates</p>
        <form method="post" action="{{ route('talent-pools.candidates.store', $pool) }}" style="display:grid;gap:8px;margin-bottom:14px">
            @csrf
            <select name="candidate_id" required>
                <option value="">Add candidate</option>
                @foreach($candidates as $candidate)
                    <option value="{{ $candidate->id }}">{{ $candidate->full_name }} · {{ $candidate->title }}</option>
                @endforeach
            </select>
            <input name="notes" placeholder="Pool note">
            <button class="btn btn-light">Save to Pool</button>
        </form>
        @forelse($pool->candidates->take(8) as $candidate)
            <div style="display:flex;justify-content:space-between;gap:8px;border-top:1px solid var(--line);padding:8px 0">
                <a href="{{ route('candidates.show', $candidate) }}">{{ $candidate->full_name }}</a>
                <form method="post" action="{{ route('talent-pools.candidates.destroy', [$pool, $candidate]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-light" style="padding:5px 8px">Remove</button>
                </form>
            </div>
        @empty
            <p class="muted">No candidates saved yet.</p>
        @endforelse
    </section>
@endforeach
</div>
@endsection
