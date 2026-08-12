@extends('layouts.app')
@section('title', 'Bias Monitoring')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Bias Monitoring</h2>
    <p class="muted">
        Aggregate pipeline pass-through rates by nationality — the only demographic field this
        system collects today. Groups smaller than {{ $minGroupSize }} candidates are folded into
        "Other (small groups)" so no individual is identifiable. This is a monitoring aid, not a
        compliance certification — treat findings as a prompt for HR/legal review, not a
        standalone hiring decision.
    </p>
</section>

<section class="card" style="margin-top:18px">
    <table>
        <thead><tr><th>Nationality</th><th>Applied</th><th>Shortlisted+</th><th>Interviewed+</th><th>Hired</th><th>Shortlist Rate</th><th>Hire Rate</th></tr></thead>
        <tbody>
        @forelse($funnel as $nationality => $counts)
            <tr>
                <td>{{ $nationality }}</td>
                <td>{{ $counts['applied'] }}</td>
                <td>{{ $counts['shortlisted'] }}</td>
                <td>{{ $counts['interviewed'] }}</td>
                <td>{{ $counts['hired'] }}</td>
                <td>{{ $counts['applied'] > 0 ? round($counts['shortlisted'] / $counts['applied'] * 100) : 0 }}%</td>
                <td>{{ $counts['applied'] > 0 ? round($counts['hired'] / $counts['applied'] * 100) : 0 }}%</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No application data yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
