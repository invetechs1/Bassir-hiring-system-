@extends('layouts.app')
@section('title', 'CV Bank')
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <h2 style="margin-top:0">
        {{ $isArabic ? 'بنك السير الذاتية' : 'CV Bank' }}
    </h2>
    <p class="muted">
        {{ $isArabic
            ? 'كل سيرة ذاتية تُرفع تُصنَّف تلقائياً حسب التخصص وتُخزَّن في مجلد مخصص لسحب المرشحين المناسبين بسرعة.'
            : 'Every uploaded CV is automatically routed into a specialty folder so you can pull the right candidates in seconds.' }}
    </p>
    <p class="muted"><strong>{{ number_format($totalIndexed) }}</strong>
        {{ $isArabic ? 'سيرة ذاتية مصنفة في البنك' : 'CVs indexed across specialties' }}
    </p>
    @if(session('status'))<div class="badge" style="background:#dcfce7;color:#166534">{{ session('status') }}</div>@endif
</section>

<div class="grid grid-3" style="margin-top:18px">
@foreach($specialties as $spec)
    <a href="{{ route('cv-bank.show', $spec['slug']) }}" style="display:block">
        <section class="card" style="height:100%">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                <div>
                    <h3 style="margin:0;color:var(--ink)">{{ $isArabic ? $spec['name_ar'] : $spec['name'] }}</h3>
                    <p class="muted" style="margin:6px 0 0">{{ $isArabic ? $spec['name'] : $spec['name_ar'] }}</p>
                </div>
                <span class="badge" style="background:{{ $spec['count'] > 0 ? '#ccfbf1' : '#f1f5f9' }};color:{{ $spec['count'] > 0 ? '#115e59' : '#64748b' }}">{{ number_format($spec['count']) }}</span>
            </div>
            <p class="muted" style="margin-top:14px">
                {{ $isArabic ? 'مجلد التخزين' : 'Storage folder' }}:
                <code>private/cv-bank/{{ $spec['slug'] }}/</code>
            </p>
        </section>
    </a>
@endforeach
</div>
@endsection
