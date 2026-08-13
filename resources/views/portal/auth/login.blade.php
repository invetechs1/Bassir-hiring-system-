@extends('portal.layout')
@section('title', 'Log In')
@section('content')
<section class="card" style="max-width:420px;margin:0 auto">
    <h2 style="margin-top:0">Log In</h2>
    <p class="muted">{{ $company->name }} candidate portal</p>
    <form method="post" action="{{ route('portal.login.post', $company->slug) }}">
        @csrf
        <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email') }}" required></div>
        <div class="field" style="margin-top:12px"><label>Password</label><input name="password" type="password" required></div>
        <button class="btn btn-dark" style="margin-top:16px;width:100%">Log In</button>
    </form>
    <p class="muted" style="margin-top:14px">New here? <a href="{{ route('portal.register', $company->slug) }}">Create an account</a></p>
</section>
@endsection
