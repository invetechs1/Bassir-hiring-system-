@extends('emails.layout')
@section('content')
<p>Dear {{ $candidateName }},</p>
<p>As the next step in your application, please complete: <strong>{{ $assessmentTitle }}</strong>.</p>
@if($description)
<p>{{ $description }}</p>
@endif
<p>Log in to your candidate portal to get started: <a href="{{ $portalUrl }}">{{ $portalUrl }}</a></p>
@endsection
