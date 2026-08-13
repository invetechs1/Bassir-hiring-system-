<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Careers')</title>
    <style>
        :root{--ink:#14213d;--teal:#0f766e;--soft:#f7f8fb;--line:#e2e8f0}
        *{box-sizing:border-box} body{margin:0;background:var(--soft);font-family:Inter,Arial,sans-serif;color:#172033}
        a{color:inherit;text-decoration:none}
        .top{background:#fff;border-bottom:1px solid var(--line);padding:16px 30px;display:flex;align-items:center;justify-content:space-between}
        .brand{font-weight:800;color:var(--ink);font-size:18px}
        .content{max-width:960px;margin:0 auto;padding:28px 20px}
        .card{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 18px 50px rgba(20,33,61,.06);padding:20px}
        .grid{display:grid;gap:16px}.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
        .muted{color:#64748b;font-size:14px}
        .btn{display:inline-block;border:0;border-radius:7px;background:var(--teal);color:#fff;font-weight:750;padding:10px 14px;cursor:pointer}
        .btn-dark{background:var(--ink)} .btn-light{background:#fff;color:#334155;border:1px solid var(--line)}
        input,select,textarea{width:100%;border:1px solid #cbd5e1;border-radius:7px;padding:10px;font:inherit}
        label{font-weight:700;font-size:14px} .field{display:grid;gap:7px}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{text-align:left;border-bottom:1px solid var(--line);padding:12px}
        th{font-size:12px;color:#64748b;text-transform:uppercase;background:#f1f5f9}
        .badge{display:inline-block;border-radius:999px;background:#ccfbf1;color:#115e59;padding:5px 9px;font-size:12px;font-weight:800}
        .nav a{margin-left:14px;font-weight:650}
        @media(max-width:700px){.grid-3,.grid-2{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="top">
    <a class="brand" href="{{ Route::has('portal.jobs.index') && isset($company) ? route('portal.jobs.index', $company->slug) : url('/') }}">{{ $company->name ?? 'Careers' }}</a>
    <nav class="nav">
        @auth('candidate')
            <a href="{{ route('portal.dashboard') }}">My Applications</a>
            <a href="{{ route('portal.assessments.index') }}">Assessments</a>
            <form method="post" action="{{ route('portal.logout') }}" style="display:inline">@csrf<button class="btn btn-light" style="margin-left:14px">Logout</button></form>
        @else
            @if(isset($company))
                <a href="{{ route('portal.login', $company->slug) }}">Log In</a>
                <a href="{{ route('portal.register', $company->slug) }}">Register</a>
            @endif
        @endauth
    </nav>
</header>
<main class="content">
    @if(session('status'))<div class="card" style="margin-bottom:16px;color:#115e59">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="card" style="margin-bottom:16px;color:#991b1b"><strong>{{ $errors->first() }}</strong></div>
    @endif
    @yield('content')
    <footer class="muted" style="margin-top:22px;text-align:center">Powered by Bassir Technology · <a href="{{ route('privacy') }}">Privacy Notice</a></footer>
</main>
</body>
</html>
