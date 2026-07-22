@extends('layouts.app')
@section('title', 'Candidate Profile')
@section('content')
<div class="grid grid-3">
    <section class="card" style="grid-column:span 2">
        <h2>{{ $candidate->full_name }}</h2>
        <p class="muted">{{ $candidate->title }} · {{ $candidate->current_company ?: 'Company not set' }} · {{ $candidate->city }} · {{ $candidate->country }} · {{ $candidate->years_experience }} years</p>
        <p>{{ $candidate->ai_summary }}</p>
        <p>
            <span class="badge">Quality {{ $candidate->quality_score ?? 0 }}%</span>
            <span class="badge">CV {{ $candidate->cv_completeness_score ?? 0 }}%</span>
            @if($candidate->industry)<span class="badge">{{ $candidate->industry }}</span>@endif
        </p>
        <a class="btn btn-light" href="{{ route('candidates.job-matches', $candidate) }}">View Job Matches</a>
        <h3>Skills</h3>
        @foreach($candidate->skills as $skill)<span class="badge">{{ $skill->name }}</span> @endforeach
        <h3 style="margin-top:14px">Languages</h3>
        @foreach($candidate->languages as $language)<span class="badge">{{ $language->name }}</span> @endforeach
    </section>
    <aside class="card">
        <p><strong>Email:</strong> {{ $candidate->email }}</p>
        <p><strong>Phone:</strong> {{ $candidate->phone }}</p>
        <p><strong>Nationality:</strong> {{ $candidate->nationality ?? '-' }}</p>
        <p><strong>Expected Salary:</strong> {{ $candidate->expected_salary ? number_format((float) $candidate->expected_salary).' SAR' : '-' }}</p>
        <p><strong>Notice Period:</strong> {{ $candidate->notice_period ?: $candidate->availability ?: '-' }}</p>
        <p><strong>Status:</strong> <span class="badge">{{ $candidate->status }}</span></p>
        <p><strong>Consent:</strong> {{ $candidate->consent_status }}</p>
        <p><strong>Contact Allowed:</strong> {{ $candidate->contact_allowed ? 'Yes' : 'No' }}</p>
        <p><strong>Consent Date:</strong> {{ $candidate->consent_captured_at?->format('Y-m-d') ?? '-' }}</p>
        @if(auth()->user()->hasPermission('candidate.write'))
        <form method="post" action="{{ route('candidates.action', $candidate) }}">
            @csrf
            <div class="field"><label>Status</label><select name="status"><option>{{ $candidate->status }}</option><option>SHORTLISTED</option><option>INTERVIEW</option><option>OFFER</option><option>HIRED</option><option>REJECTED</option><option>BLACKLISTED</option></select></div>
            <div class="field"><label>Tags</label><input name="tags" placeholder="urgent; senior"></div>
            <div class="field"><label>Note</label><textarea name="note"></textarea></div>
            <div class="field"><label>Communication Channel</label><select name="communication_channel"><option value="">No communication log</option><option>Email</option><option>WhatsApp</option><option>Phone</option><option>SMS</option><option>LinkedIn</option><option>Other</option></select></div>
            <div class="field"><label>Direction</label><select name="communication_direction"><option value="">-</option><option>OUTBOUND</option><option>INBOUND</option></select></div>
            <div class="field"><label>Subject</label><input name="communication_subject"></div>
            <div class="field"><label>Message</label><textarea name="communication_body"></textarea></div>
            <button class="btn" style="margin-top:12px">Save Action</button>
        </form>
        @endif
    </aside>
</div>
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Experience</th><th>Company</th><th>Timeline</th></tr></thead>
        <tbody>
        @forelse($candidate->experience as $item)
            <tr>
                <td><strong>{{ $item->title }}</strong><br><span class="muted">{{ $item->summary }}</span></td>
                <td>{{ $item->company }}</td>
                <td>{{ optional($item->start_date)->format('Y-m') ?? '-' }} → {{ $item->is_current ? 'Present' : (optional($item->end_date)->format('Y-m') ?? '-') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No structured experience records yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div class="grid grid-2" style="margin-top:18px">
    <section class="card">
        <h3>Education</h3>
        @forelse($candidate->education as $item)
            <p><strong>{{ $item->institution }}</strong><br><span class="muted">{{ $item->degree }} {{ $item->end_year ? '· '.$item->end_year : '' }}</span></p>
        @empty
            <p class="muted">No education records yet.</p>
        @endforelse
    </section>
    <section class="card">
        <h3>Certifications</h3>
        @forelse($candidate->certifications as $item)
            <p><strong>{{ $item->name }}</strong><br><span class="muted">{{ $item->issuer }} {{ $item->issue_date ? '· '.$item->issue_date->format('Y-m-d') : '' }}</span></p>
        @empty
            <p class="muted">No certification records yet.</p>
        @endforelse
    </section>
</div>
<div class="grid grid-2" style="margin-top:18px">
    <section class="card">
        <h3>Source and Documents</h3>
        @foreach($candidate->sources as $source)
            <p><strong>{{ $source->source_type }}</strong><br><span class="muted">{{ $source->source_url ?: $source->consent_note }}</span></p>
        @endforeach
        @foreach($candidate->documents as $document)
            <p>
                <span class="badge">{{ $document->scan_status }}</span>
                <span class="badge">{{ $document->malware_scan_status ?? 'NOT_CONFIGURED' }}</span>
                <a href="{{ route('candidates.documents.download', [$candidate, $document]) }}">{{ $document->file_name }}</a>
                <span class="muted">Downloads: {{ $document->download_count ?? 0 }}</span>
            </p>
        @endforeach
    </section>
    <section class="card">
        <h3>Timeline</h3>
        @foreach($candidate->notes->sortByDesc('created_at')->take(6) as $note)
            <p><strong>{{ $note->created_at?->format('Y-m-d H:i') }}</strong><br>{{ $note->body }}</p>
        @endforeach
        @if($candidate->notes->isEmpty())
            <p class="muted">No timeline notes yet.</p>
        @endif
    </section>
</div>
<section class="card" style="margin-top:18px">
    <h3>Talent Pools</h3>
    @forelse($candidate->talentPools as $pool)
        <span class="badge">{{ $pool->category }} · {{ $pool->name }}</span>
    @empty
        <p class="muted">Not saved in any talent pool yet.</p>
    @endforelse
</section>
<section class="card" style="margin-top:18px;padding:0;overflow:auto">
    <table>
        <thead><tr><th>Communication</th><th>Direction</th><th>Subject</th><th>Message</th><th>Timestamp</th></tr></thead>
        <tbody>
        @forelse($candidate->communications->sortByDesc('sent_at')->take(12) as $item)
            <tr>
                <td>{{ $item->channel }}</td>
                <td>{{ $item->direction }}</td>
                <td>{{ $item->subject ?: '-' }}</td>
                <td>{{ $item->body }}</td>
                <td>{{ $item->sent_at ?: $item->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No communication records yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
