@extends('layouts.app')
@section('title', 'Sourcing Agents')
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
            <h2 style="margin:0">{{ $isArabic ? 'وكلاء التوظيف' : 'Sourcing Agents' }}</h2>
            <p class="muted" style="margin:6px 0 0">
                {{ $isArabic
                    ? 'كل وكيل يعمل كموظف موارد بشرية متخصص. يبحث عبر واجهات البحث الرسمية (جوجل، بينج، سيرب) وشركاء التوظيف الرسميين، يحمل السير الذاتية العامة، يقيمها حسب مواصفاتك، ثم يضيف المطابقين لبنك السير الذاتية.'
                    : 'Each agent acts like a specialist HR employee. It searches the official Google/Bing/SerpAPI + partner APIs, downloads public CVs, scores them against your brief, and only adds the passing ones to the CV bank.' }}
            </p>
        </div>
        <a href="{{ route('sourcing-agents.create') }}" class="btn">
            {{ $isArabic ? '+ توظيف وكيل جديد' : '+ Hire New Agent' }}
        </a>
    </div>
    @if(session('status'))<div class="badge" style="margin-top:10px;background:#dcfce7;color:#166534">{{ session('status') }}</div>@endif
</section>

<div class="grid grid-3" style="margin-top:18px">
@forelse($agents as $agent)
    <section class="card" style="height:100%">
        <div style="display:flex;gap:10px;align-items:center">
            <div style="font-size:36px">{{ $agent->avatar_emoji }}</div>
            <div style="flex:1">
                <h3 style="margin:0"><a href="{{ route('sourcing-agents.show', $agent) }}">{{ $agent->name }}</a></h3>
                <p class="muted" style="margin:4px 0 0">
                    {{ $isArabic ? $agent->personaLabelAr() : $agent->personaLabel() }}
                    · <strong>{{ $agent->specialty_name }}</strong>
                </p>
            </div>
            <span class="badge" style="background:{{ $agent->is_active ? '#ccfbf1' : '#f1f5f9' }};color:{{ $agent->is_active ? '#115e59' : '#64748b' }}">
                {{ $agent->is_active ? ($isArabic ? 'نشط' : 'Active') : ($isArabic ? 'موقوف' : 'Paused') }}
            </span>
        </div>
        <div class="grid grid-2" style="margin-top:12px;gap:8px">
            <div>
                <div class="muted" style="font-size:12px">{{ $isArabic ? 'مرشحون مضافون' : 'Picks' }}</div>
                <div class="kpi" style="font-size:22px">{{ number_format($agent->candidates_added) }}</div>
            </div>
            <div>
                <div class="muted" style="font-size:12px">{{ $isArabic ? 'متوسط الدرجة' : 'Avg score' }}</div>
                <div class="kpi" style="font-size:22px">{{ $agent->avg_score }}/100</div>
            </div>
        </div>
        <p class="muted" style="margin-top:10px">
            {{ $isArabic ? 'الجدولة' : 'Schedule' }}: <strong>{{ $agent->frequency }}</strong>
            @if($agent->last_run_at) · {{ $isArabic ? 'آخر تشغيل' : 'Last run' }} {{ $agent->last_run_at->diffForHumans() }} @endif
        </p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px">
            <a href="{{ route('sourcing-agents.show', $agent) }}" class="btn btn-light" style="padding:6px 10px">
                {{ $isArabic ? 'التفاصيل' : 'Details' }}
            </a>
            <form method="post" action="{{ route('sourcing-agents.run', $agent) }}" style="display:inline">
                @csrf
                <button class="btn" style="padding:6px 10px">{{ $isArabic ? 'شغّل الآن' : 'Run now' }}</button>
            </form>
        </div>
    </section>
@empty
    <section class="card">
        <p class="muted" style="margin:0">
            {{ $isArabic
                ? 'لا يوجد وكلاء توظيف بعد. أنشئ وكيلاً واحداً لكل تخصص (مهندس مدني، مطور برمجيات، محاسب...) ودعه يعمل نيابة عنك.'
                : 'No agents yet. Hire one per specialty (Civil Engineer, Software Engineer, Accountant…) and let it work on your behalf.' }}
        </p>
    </section>
@endforelse
</div>
@endsection
