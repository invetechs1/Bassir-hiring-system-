@extends('layouts.app')
@section('title', 'Hire Sourcing Agent')
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <h2 style="margin-top:0">{{ $isArabic ? 'توظيف وكيل جديد' : 'Hire a Sourcing Agent' }}</h2>
    <form method="post" action="{{ route('sourcing-agents.store') }}">
        @csrf
        @include('sourcing-agents._form')
        <div style="margin-top:16px;display:flex;gap:8px">
            <button class="btn">{{ $isArabic ? 'توظيف' : 'Hire Agent' }}</button>
            <a class="btn btn-light" href="{{ route('sourcing-agents.index') }}">{{ $isArabic ? 'إلغاء' : 'Cancel' }}</a>
        </div>
    </form>
</section>
@if($errors->any())
<section class="card" style="margin-top:12px">
    @foreach($errors->all() as $e)<div class="muted">• {{ $e }}</div>@endforeach
</section>
@endif
@endsection
