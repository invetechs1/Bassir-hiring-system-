@extends('emails.layout')
@section('content')
<p>Dear {{ $candidateName }},</p>
<p>Thank you for your interest in <strong>{{ $jobTitle }}</strong> and for the time you invested in the application process.</p>
<p>After careful consideration, we have decided to move forward with other candidates for this role. We encourage you to apply for future opportunities that match your background.</p>
<p>We wish you the best in your job search.</p>
@endsection
