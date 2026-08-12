@extends('emails.layout')
@section('content')
<p>Dear {{ $candidateName }},</p>
@if($isReminder)
<p>This is a reminder about your upcoming interview for <strong>{{ $jobTitle }}</strong>.</p>
@else
<p>You have been invited to interview for <strong>{{ $jobTitle }}</strong>.</p>
@endif
<p><strong>Date/time:</strong> {{ $startsAt?->format('l, F j, Y \a\t g:i A') }}</p>
<p><strong>Channel:</strong> {{ $channel ?? 'To be confirmed' }}</p>
@if($meetingLink)
<p><strong>Link:</strong> <a href="{{ $meetingLink }}">{{ $meetingLink }}</a></p>
@endif
<p>Please confirm your availability. If you need to reschedule, contact your recruiter as soon as possible.</p>
@endsection
