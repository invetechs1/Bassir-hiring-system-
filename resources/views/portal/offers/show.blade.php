@extends('portal.layout')
@section('title', 'Your Offer')
@section('content')
<section class="card">
    <h2 style="margin-top:0">Offer: {{ $offer->job->title }}</h2>
    <p><strong>Salary:</strong> {{ number_format((float) $offer->salary_amount, 2) }} {{ $offer->currency }}</p>
    @if($offer->start_date)<p><strong>Start date:</strong> {{ $offer->start_date->format('F j, Y') }}</p>@endif
    @if($offer->employment_type)<p><strong>Employment type:</strong> {{ $offer->employment_type }}</p>@endif
    @if($offer->terms)<p><strong>Terms:</strong><br>{{ $offer->terms }}</p>@endif
    <p style="margin-top:10px">Status: <span class="badge">{{ $offer->status }}</span></p>

    @if($offer->status === 'SENT')
        <div style="display:flex;gap:12px;margin-top:18px">
            <form method="post" action="{{ route('portal.offers.accept', $offer) }}">@csrf<button class="btn btn-dark">Accept Offer</button></form>
            <form method="post" action="{{ route('portal.offers.decline', $offer) }}">@csrf<button class="btn btn-light">Decline</button></form>
        </div>
    @endif
</section>
@endsection
