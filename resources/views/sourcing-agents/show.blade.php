@extends('layouts.app')
@section('title', $agent->name)
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="display:flex;gap:12px;align-items:center">
            <div style="font-size:44px">{{ $agent->avatar_emoji }}</div>
            <div>
                <h2 style="margin:0">{{ $agent->name }}</h2>
                <p class="muted" style="margin:6px 0 0">
                    {{ $isArabic ? $agent->personaLabelAr() : $agent->personaLabel() }}
                    · <strong>{{ $agent->specialty_name }}</strong>
                    · {{ $agent->frequency }}
                </p>
            </div>
        </div>
        <div style="display:flex;gap:6px">
            <form method="post" action="{{ route('sourcing-agents.run', $agent) }}">
                @csrf
                <button class="btn">{{ $isArabic ? 'شغّل الآن' : 'Run now' }}</button>
            </form>
            <a class="btn btn-light" href="{{ route('sourcing-agents.edit', $agent) }}">{{ $isArabic ? 'تعديل' : 'Edit' }}</a>
        </div>
    </div>
    @if(session('status'))<div class="badge" style="margin-top:10px;background:#dcfce7;color:#166534">{{ session('status') }}</div>@endif
    @if($agent->bio)<p class="muted" style="margin-top:14px">{{ $agent->bio }}</p>@endif
</section>

<div class="grid grid-4" style="margin-top:18px">
    <div class="card"><div class="muted">{{ $isArabic ? 'التشغيلات' : 'Runs' }}</div><div class="kpi">{{ $agent->runs_count }}</div></div>
    <div class="card"><div class="muted">{{ $isArabic ? 'مرشحون مضافون' : 'Candidates added' }}</div><div class="kpi">{{ $agent->candidates_added }}</div></div>
    <div class="card"><div class="muted">{{ $isArabic ? 'متوسط الدرجة' : 'Avg score' }}</div><div class="kpi">{{ $agent->avg_score }}/100</div></div>
    <div class="card"><div class="muted">{{ $isArabic ? 'التشغيل القادم' : 'Next run' }}</div><div class="kpi" style="font-size:20px">{{ $agent->next_run_at?->diffForHumans() ?? '—' }}</div></div>
</div>

<div class="grid grid-2" style="margin-top:18px">
    <section class="card">
        <h3 style="margin-top:0">{{ $isArabic ? 'أفضل الاختيارات' : 'Top picks' }}</h3>
        @if($picks->count() === 0)
            <p class="muted">{{ $isArabic ? 'لا اختيارات بعد. شغّل الوكيل مرة.' : 'No picks yet. Run the agent once.' }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>{{ $isArabic ? 'الاسم' : 'Name' }}</th>
                        <th>{{ $isArabic ? 'المسمى' : 'Title' }}</th>
                        <th>{{ $isArabic ? 'الدرجة' : 'Score' }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($picks as $c)
                    <tr>
                        <td><a href="{{ route('candidates.show', $c) }}">{{ $c->full_name }}</a></td>
                        <td>{{ $c->title }}</td>
                        <td><span class="badge">{{ $c->pivot->score }}/100</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="card">
        <h3 style="margin-top:0">{{ $isArabic ? 'سجل النشاط' : 'Activity log' }}</h3>
        @if($agent->runs->isEmpty())
            <p class="muted">{{ $isArabic ? 'لا سجل بعد.' : 'No activity yet.' }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ $isArabic ? 'التاريخ' : 'When' }}</th>
                        <th>{{ $isArabic ? 'مسحت' : 'Scanned' }}</th>
                        <th>{{ $isArabic ? 'أضيفت' : 'Added' }}</th>
                        <th>{{ $isArabic ? 'الحالة' : 'Status' }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($agent->runs as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->started_at?->diffForHumans() ?? '—' }}</td>
                        <td>{{ $r->results_scanned }}</td>
                        <td>{{ $r->candidates_added }} <span class="muted">(avg {{ $r->avg_score }})</span></td>
                        <td><span class="badge" style="background:{{ $r->status === 'SUCCESS' ? '#dcfce7' : ($r->status === 'FAILED' ? '#fee2e2' : '#f1f5f9') }};color:{{ $r->status === 'SUCCESS' ? '#166534' : ($r->status === 'FAILED' ? '#991b1b' : '#334155') }}">{{ $r->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
