@extends('portal.layout')
@section('title', 'Register')
@section('content')
<section class="card" style="max-width:480px;margin:0 auto">
    <h2 style="margin-top:0">Create Your Candidate Account</h2>
    <p class="muted">Register to apply for jobs at {{ $company->name }} and track your application status.</p>
    <form method="post" action="{{ route('portal.register.post', $company->slug) }}">
        @csrf
        <div class="field"><label>Full name</label><input name="full_name" value="{{ old('full_name') }}" required></div>
        <div class="field" style="margin-top:12px"><label>Email</label><input name="email" type="email" value="{{ old('email') }}" required></div>
        <div class="field" style="margin-top:12px"><label>Phone (optional)</label><input name="phone" value="{{ old('phone') }}"></div>
        <div class="field" style="margin-top:12px"><label>Password</label><input name="password" type="password" required minlength="8"></div>
        <div class="field" style="margin-top:12px"><label>Confirm password</label><input name="password_confirmation" type="password" required minlength="8"></div>
        <p class="muted" style="margin-top:12px">By registering you consent to {{ $company->name }} storing and reviewing your profile for recruitment purposes. See our <a href="{{ route('privacy') }}">Privacy Notice</a>.</p>
        <button class="btn btn-dark" style="margin-top:10px;width:100%">Create Account</button>
    </form>
    <p class="muted" style="margin-top:14px">Already registered? <a href="{{ route('portal.login', $company->slug) }}">Log in</a></p>
</section>
@endsection
