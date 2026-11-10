{{-- Why a candidate was returned by a search: relevance, matched / missing criteria, CV excerpt. --}}
@php($highlighter = app(\App\Services\CandidateSearchService::class))
@php($score = (int) ($candidate->search_score ?? 0))
<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;align-items:center">
    <span class="badge" style="background:{{ $score >= 70 ? '#dcfce7' : ($score >= 40 ? '#fef3c7' : '#f1f5f9') }};color:{{ $score >= 70 ? '#166534' : ($score >= 40 ? '#92400e' : '#475569') }}">{{ $score }}% match</span>
    <span class="muted" style="font-size:12px">{{ $candidate->search_coverage }} criteria</span>
    @foreach(($candidate->search_matches ?? []) as $match)
        <span class="badge" style="background:#ecfdf5;color:#166534;font-weight:600">{{ $match }}</span>
    @endforeach
    @foreach(($candidate->search_missing ?? []) as $missing)
        <span class="badge" style="background:#fee2e2;color:#991b1b;font-weight:600">missing: {{ $missing }}</span>
    @endforeach
</div>
@if(!empty($candidate->search_snippet))
    <div class="muted" style="font-size:12px;margin-top:6px">{!! $highlighter->highlight($candidate->search_snippet, $candidate->search_terms ?? []) !!}</div>
@endif
