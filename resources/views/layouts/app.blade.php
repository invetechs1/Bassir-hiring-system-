@php($currentLocale = app()->getLocale())
<!doctype html>
<html lang="{{ $currentLocale }}" dir="{{ $currentLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bassir AI Recruitment System')</title>
    <style>
        :root{--ink:#14213d;--teal:#0f766e;--soft:#f7f8fb;--line:#e2e8f0}
        *{box-sizing:border-box} body{margin:0;background:var(--soft);font-family:Inter,Arial,sans-serif;color:#172033}
        a{color:inherit;text-decoration:none}.layout{display:flex;min-height:100vh}.side{width:280px;background:#fff;border-right:1px solid var(--line);padding:22px;position:fixed;inset:0 auto 0 0}
        .brand{font-weight:800;color:var(--ink);font-size:18px}.tag{font-size:13px;color:#64748b;margin-top:4px}.nav{margin-top:28px;display:grid;gap:6px}.nav a{padding:11px 12px;border-radius:7px;font-weight:650;color:#334155}.nav a:hover{background:#ccfbf1;color:#115e59}
        .main{margin-left:280px;width:calc(100% - 280px)}.top{background:#fff;border-bottom:1px solid var(--line);padding:18px 30px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0}
        .content{padding:28px}.card{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 18px 50px rgba(20,33,61,.08);padding:20px}.grid{display:grid;gap:16px}.grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
        .kpi{font-size:30px;font-weight:800;color:var(--ink);margin-top:10px}.muted{color:#64748b;font-size:14px}.btn{display:inline-block;border:0;border-radius:7px;background:var(--teal);color:#fff;font-weight:750;padding:10px 14px;cursor:pointer}.btn-dark{background:var(--ink)}.btn-light{background:#fff;color:#334155;border:1px solid var(--line)}
        input,select,textarea{width:100%;border:1px solid #cbd5e1;border-radius:7px;padding:10px;font:inherit}label{font-weight:700;font-size:14px}.field{display:grid;gap:7px}table{width:100%;border-collapse:collapse;background:#fff}th,td{text-align:left;border-bottom:1px solid var(--line);padding:13px}th{font-size:12px;color:#64748b;text-transform:uppercase;background:#f1f5f9}.badge{display:inline-block;border-radius:999px;background:#ccfbf1;color:#115e59;padding:5px 9px;font-size:12px;font-weight:800}
        [dir="rtl"] .side{inset:0  auto 0 0;border-right:0;border-left:1px solid var(--line);right:0;left:auto}
        [dir="rtl"] .main{margin-left:0;margin-right:280px}
        @media(max-width:900px){.side{position:relative;width:100%;height:auto}.layout{display:block}.main{margin-left:0;margin-right:0;width:100%}.grid-4,.grid-3,.grid-2{grid-template-columns:1fr}.top{position:relative}}
    </style>
</head>
<body>
@auth
@php($userRole = auth()->user()->role?->name)
<div class="layout">
    <aside class="side">
        <div class="brand">Bassir AI Recruitment</div>
        <div class="tag">Powered by Bassir Technology</div>
        <nav class="nav">
            @if(auth()->user()->hasPermission('dashboard.view'))
            <a href="{{ route('dashboard') }}">Dashboard</a>
            @endif
            @if(auth()->user()->hasPermission('ai_search.run'))
            <a href="{{ route('ai-search.index') }}">AI Search</a>
            @endif
            @if(auth()->user()->hasPermission('candidate.read'))
            <a href="{{ route('candidates.index') }}">Candidates</a>
            <a href="{{ route('search-assistant.index') }}">Search Assistant</a>
            <a href="{{ route('comparisons.candidates') }}">Compare</a>
            @endif
            @if(auth()->user()->hasPermission('candidate.write'))
            <a href="{{ route('upload.index') }}">Upload CV</a>
            <a href="{{ route('talent-pools.index') }}">Talent Pools</a>
            @endif
            @if(auth()->user()->hasPermission('job.read'))
            <a href="{{ route('jobs.index') }}">Jobs</a>
            @endif
            @if(auth()->user()->hasPermission('candidate.read'))
            <a href="{{ route('applications.index') }}">Pipeline</a>
            @endif
            @if(auth()->user()->hasPermission('job.match'))
            <a href="{{ route('matching.index') }}">AI Matching</a>
            @endif
            @if(auth()->user()->hasPermission('specialization.manage'))
            <a href="{{ route('specializations.index') }}">Specializations</a>
            @endif
            @if(auth()->user()->hasPermission('interview.read'))
            <a href="{{ route('interviews.index') }}">Interviews</a>
            @endif
            @if(auth()->user()->hasPermission('salary.manage'))
            <a href="{{ route('salary-benchmarks.index') }}">Salary Benchmarks</a>
            @endif
            @if(auth()->user()->isSuperAdmin() && auth()->user()->hasPermission('integrations.manage'))
            <a href="{{ route('integrations.index') }}">Integrations</a>
            @endif
            @if(auth()->user()->hasPermission('reports.export'))
            <a href="{{ route('reports.index') }}">Reports</a>
            @endif
            @if(auth()->user()->hasPermission('users.manage'))
            <a href="{{ route('users.index') }}">User Management</a>
            @endif
            @if(auth()->user()->hasPermission('audit.read'))
            <a href="{{ route('audit-logs.index') }}">Audit Logs</a>
            @endif
            <a href="{{ route('settings.profile') }}">Settings</a>
        </nav>
    </aside>
    <main class="main">
        <header class="top">
            <div>
                <strong>@yield('title', 'Dashboard')</strong>
                <div class="muted">Enterprise recruitment intelligence with compliant sourcing controls · {{ $userRole }}</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <a class="btn btn-light" href="{{ route('locale.set', 'en') }}">EN</a>
                <a class="btn btn-light" href="{{ route('locale.set', 'ar') }}">AR</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-light">Logout</button></form>
            </div>
        </header>
        <section class="content">
            @if(session('status'))<div class="card" style="margin-bottom:16px;color:#115e59">{{ session('status') }}</div>@endif
            @if($errors->any())
                <div class="card" style="margin-bottom:16px;color:#991b1b">
                    <strong>Validation issue:</strong> {{ $errors->first() }}
                </div>
            @endif
            @yield('content')
            <footer class="muted" style="margin-top:22px;text-align:center">
                Powered by Bassir Technology · <a href="{{ route('privacy') }}">Privacy Notice</a>
            </footer>
        </section>
    </main>
</div>
@else
    @yield('content')
@endauth
</body>
</html>
