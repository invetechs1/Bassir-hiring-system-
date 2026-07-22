@extends('layouts.app')
@section('title', 'Specializations Management')
@section('content')
<div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:16px">
    <div>
        <h2 style="margin:0">Specializations</h2>
        <p class="muted">Manage engineering, technology, finance, HR, and operations categories.</p>
    </div>
    <a class="btn" href="{{ route('specializations.create') }}">Add Specialization</a>
</div>
<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Name</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($specializations as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong><br><span class="muted">{{ $item->description }}</span></td>
                <td>{{ $item->category }}</td>
                <td><span class="badge">{{ $item->is_active ? 'Active' : 'Disabled' }}</span></td>
                <td style="display:flex;gap:8px">
                    <a class="btn btn-light" href="{{ route('specializations.edit', $item) }}">Edit</a>
                    <form method="post" action="{{ route('specializations.destroy', $item) }}">
                        @csrf
                        @method('delete')
                        <button class="btn btn-dark">Disable</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $specializations->links() }}</div>
@endsection
