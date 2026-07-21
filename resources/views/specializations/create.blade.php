@extends('layouts.app')
@section('title', $specialization->exists ? 'Edit Specialization' : 'Add Specialization')
@section('content')
<form method="post" action="{{ $specialization->exists ? route('specializations.update', $specialization) : route('specializations.store') }}" class="card">
    @csrf
    @if($specialization->exists) @method('put') @endif
    <div class="grid grid-3">
        <div class="field"><label>Name</label><input name="name" value="{{ old('name', $specialization->name) }}" required></div>
        <div class="field"><label>Category</label><select name="category">
            @foreach(['Engineering','Technology','Finance','HR','Operations','Administration','Custom'] as $category)
                <option value="{{ $category }}" @selected(old('category', $specialization->category) === $category)>{{ $category }}</option>
            @endforeach
        </select></div>
        <div class="field"><label>Status</label><select name="is_active">
            <option value="1" @selected($specialization->is_active)>Active</option>
            <option value="0" @selected(!$specialization->is_active)>Disabled</option>
        </select></div>
    </div>
    <div class="field" style="margin-top:14px"><label>Description</label><textarea name="description">{{ old('description', $specialization->description) }}</textarea></div>
    <button class="btn" style="margin-top:18px">{{ $specialization->exists ? 'Update' : 'Create' }} Specialization</button>
</form>
@endsection
