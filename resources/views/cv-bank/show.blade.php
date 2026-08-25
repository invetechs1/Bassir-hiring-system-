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
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ $isArabic ? 'بحث بالاسم أو المسمى أو المدينة' : 'Search by name, title, or city' }}" style="flex:1;min-width:220px">
        <button class="btn" type="submit">{{ $isArabic ? 'بحث' : 'Search' }}</button>
    </form>
    @if(session('status'))<div class="badge" style="margin-top:10px;background:#dcfce7;color:#166534">{{ session('status') }}</div>@endif
</section>

<section class="card" style="margin-top:18px">
    @if($candidates->count() === 0)
        <p class="muted" style="margin:0">{{ $isArabic ? 'لا يوجد مرشحون في هذا التخصص بعد.' : 'No candidates in this specialty yet.' }}</p>
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
            @php($doc = $candidate->documents->first())
            <tr>
                <td><a href="{{ route('candidates.show', $candidate) }}">{{ $candidate->full_name }}</a></td>
                <td>{{ $candidate->title }}</td>
                <td>{{ $candidate->city ?? '—' }}</td>
                <td>{{ $candidate->years_experience }}y</td>
                <td>
                    @if($doc)
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
