@extends('layouts.app')
@section('title', 'Edit Agent — '.$agent->name)
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <h2 style="margin-top:0">{{ $isArabic ? 'تعديل الوكيل' : 'Edit agent' }}: {{ $agent->name }}</h2>
    <form method="post" action="{{ route('sourcing-agents.update', $agent) }}">
        @csrf @method('PUT')
        @include('sourcing-agents._form')
        <div style="margin-top:16px;display:flex;gap:8px">
            <button class="btn">{{ $isArabic ? 'حفظ' : 'Save' }}</button>
            <a class="btn btn-light" href="{{ route('sourcing-agents.show', $agent) }}">{{ $isArabic ? 'إلغاء' : 'Cancel' }}</a>
        </div>
    </form>
    <form method="post" action="{{ route('sourcing-agents.destroy', $agent) }}" style="margin-top:14px" onsubmit="return confirm('{{ $isArabic ? 'حذف هذا الوكيل؟' : 'Retire this agent?' }}')">
        @csrf @method('DELETE')
        <button class="btn btn-light" style="color:#b91c1c;border-color:#fecaca">{{ $isArabic ? 'إنهاء خدمة الوكيل' : 'Retire agent' }}</button>
    </form>
</section>
@if($errors->any())
<section class="card" style="margin-top:12px">
    @foreach($errors->all() as $e)<div class="muted">• {{ $e }}</div>@endforeach
</section>
@endif
@endsection
