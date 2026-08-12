@extends('layouts.app')
@section('title', 'Offers')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Offers</h2>
    <p class="muted">Draft, approve, and send offers. Accepting an offer automatically starts onboarding.</p>
</section>

<section class="card" style="margin-top:18px">
    <table>
        <thead><tr><th>Candidate</th><th>Job</th><th>Salary</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($offers as $offer)
            <tr>
                <td>{{ $offer->candidate->full_name }}</td>
                <td>{{ $offer->job->title }}</td>
                <td>{{ number_format((float) $offer->salary_amount, 2) }} {{ $offer->currency }}</td>
                <td><span class="badge">{{ $offer->status }}</span></td>
                <td style="display:flex;gap:8px">
                    @if($offer->status === 'PENDING_APPROVAL' && auth()->user()->hasAnyRole(['SUPER_ADMIN', 'COMPANY_ADMIN', 'HR_MANAGER']))
                        <form method="post" action="{{ route('offers.approve', $offer) }}">@csrf<button class="btn btn-light">Approve</button></form>
                    @endif
                    @if($offer->status === 'APPROVED')
                        <form method="post" action="{{ route('offers.send', $offer) }}">@csrf<button class="btn">Send to Candidate</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No offers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
