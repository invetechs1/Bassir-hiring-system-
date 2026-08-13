@extends('emails.layout')
@section('content')
<p>Dear {{ $candidateName }},</p>
<p>Thank you for applying for <strong>{{ $jobTitle }}</strong>. Your application has been received and will be reviewed by {{ $companyName }}.</p>
<p>We will contact you with an update as your application progresses through our review process.</p>
<p>Best regards,<br>{{ $companyName }}</p>
@endsection
