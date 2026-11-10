@extends('layouts.app')
@section('title', ($specialty['name'] ?? 'CV Bank').' — CV Bank')
@section('content')
@php($isArabic = app()->getLocale() === 'ar')
<section class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
            <a href="{{ route('cv-bank.index') }}" class="muted">← {{ $isArabic ? 'كل التخصصات' : 'All specialties' }}</a>
            <h2 style="margin:6px 0 0">
                {{ $isArabic ? $specialty['name_ar'] : $specialty['name'] }}
            </h2>
            <p class="muted" style="margin:6px 0 0">
                {{ $isArabic ? $specialty['name'] : $specialty['name_ar'] }}
                · <code>private/cv-bank/{{ $specialty['slug'] }}/</code>
            </p>
        </div>
        <div>
            <span class="badge">{{ number_format($candidates->total()) }} {{ $isArabic ? 'مرشح' : 'candidates' }}</span>
        </div>
    </div>
    <form method="get" style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap">
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ $isArabic ? 'بحث بالاسم أو المهارة أو المدينة أو نص السيرة' : 'Search name, skill, city or CV text, e.g. Revit Navisworks Riyadh' }}" style="flex:1;min-width:220px">
        <select name="mode" style="width:auto">
            <option value="balanced" @selected(($mode ?? 'balanced') === 'balanced')>{{ $isArabic ? 'أفضل تطابق' : 'Best matches' }}</option>
            <option value="all" @selected(($mode ?? '') === 'all')>{{ $isArabic ? 'كل الكلمات' : 'Match every term' }}</option>
            <option value="any" @selected(($mode ?? '') === 'any')>{{ $isArabic ? 'أي كلمة' : 'Match any term' }}</option>
        </select>
        <button class="btn" type="submit">{{ $isArabic ? 'بحث' : 'Search' }}</button>
    </form>
    @if(session('status'))<div class="badge" style="margin-top:10px;background:#dcfce7;color:#166534">{{ session('status') }}</div>@endif
</section>

<section class="card" style="margin-top:18px">
    @if($candidates->count() === 0)
        <p class="muted" style="margin:0">{{ $q !== '' ? ($isArabic ? 'لا توجد نتائج مطابقة لبحثك في هذا التخصص.' : 'No CVs in this specialty matched your search. Try fewer words or "Match any term".') : ($isArabic ? 'لا يوجد مرشحون في هذا التخصص بعد.' : 'No candidates in this specialty yet.') }}</p>
    @else
    <table>
        <thead>
            <tr>
                <th>{{ $isArabic ? 'الاسم' : 'Name' }}</th>
                <th>{{ $isArabic ? 'المسمى' : 'Title' }}</th>
                <th>{{ $isArabic ? 'المدينة' : 'City' }}</th>
                <th>{{ $isArabic ? 'الخبرة' : 'Experience' }}</th>
                <th>{{ $isArabic ? 'السيرة الذاتية' : 'CV' }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($candidates as $candidate)
            @php($doc = $candidate->documents->sortByDesc('id')->first())
            <tr>
                <td>
                    <a href="{{ route('candidates.show', array_filter(['candidate' => $candidate, 'hl' => $q !== '' ? $q : null])) }}">{{ $candidate->full_name }}</a>
                    @if($q !== '')@include('partials.search-match', ['candidate' => $candidate])@endif
                </td>
                <td>{{ $candidate->title }}
                    @if($candidate->skills->isNotEmpty())<div style="margin-top:4px">@foreach($candidate->skills->take(5) as $skill)<span class="badge" style="background:#f1f5f9;color:#475569;font-weight:600">{{ $skill->name }}</span> @endforeach</div>@endif
                </td>
                <td>{{ $candidate->city ?? '—' }}</td>
                <td>{{ $candidate->years_experience }}y</td>
                <td>
                    @if($doc)
                        <a href="{{ route('candidates.documents.view', [$candidate, $doc]) }}" target="_blank" rel="noopener" class="btn" style="padding:6px 10px">{{ $isArabic ? 'فتح' : 'Open' }}</a>
                        <a href="{{ route('candidates.documents.download', [$candidate, $doc]) }}" class="btn btn-light" style="padding:6px 10px">{{ $isArabic ? 'تنزيل' : 'Download' }}</a>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td>
                    @if(auth()->user()->hasPermission('candidate.write'))
                    <form method="post" action="{{ route('cv-bank.reclassify', $candidate) }}" style="display:flex;gap:6px">
                        @csrf
                        <input name="specialization" placeholder="{{ $isArabic ? 'إعادة التصنيف' : 'Reclassify to…' }}" style="min-width:160px">
                        <button class="btn btn-light" style="padding:6px 10px">{{ $isArabic ? 'حفظ' : 'Save' }}</button>
                    </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:14px">{{ $candidates->links() }}</div>
    @endif
</section>
@endsection
