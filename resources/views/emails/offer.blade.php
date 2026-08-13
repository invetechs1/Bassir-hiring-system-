@extends('emails.layout')
@section('content')
<p>Dear {{ $candidateName }},</p>
<p>Congratulations! We are pleased to offer you the position of <strong>{{ $jobTitle }}</strong>.</p>
<p><strong>Salary:</strong> {{ number_format((float) $salaryAmount, 2) }} {{ $currency }}</p>
@if($startDate)
<p><strong>Start date:</strong> {{ $startDate->format('F j, Y') }}</p>
@endif
@if($terms)
<p><strong>Terms:</strong><br>{{ $terms }}</p>
@endif
<p>Please review the attached offer letter and respond via your candidate portal at your earliest convenience: <a href="{{ $portalUrl }}">{{ $portalUrl }}</a></p>
<p>We look forward to welcoming you to the team.</p>
@endsection
