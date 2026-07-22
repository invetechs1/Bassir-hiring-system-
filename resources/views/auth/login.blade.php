@extends('layouts.app')
@section('title', 'Secure Login')
@section('content')
<main style="min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#f8fafc,#eef2ff);padding:20px">
    <form method="post" action="{{ route('login.post') }}" class="card" style="width:100%;max-width:430px">
        @csrf
        <h1 style="margin:0;color:#14213d">Bassir AI Recruitment System</h1>
        <p class="muted">Powered by Bassir Technology</p>
        @if($errors->any())<p style="color:#b91c1c">{{ $errors->first() }}</p>@endif
        <div class="field" style="margin-top:20px"><label>Username or Email</label><input name="username" value="{{ old('username') }}" required></div>
        <div class="field" style="margin-top:14px"><label>Password</label><input name="password" type="password" required></div>
        <button class="btn" style="margin-top:20px;width:100%">Secure Login</button>
        <div style="display:flex;gap:8px;margin-top:12px">
            <a class="btn btn-light" href="{{ route('locale.set', 'en') }}">EN</a>
            <a class="btn btn-light" href="{{ route('locale.set', 'ar') }}">AR</a>
        </div>
        <p class="muted">Initial setup credentials are created by the database seeder only.</p>
    </form>
</main>
@endsection
