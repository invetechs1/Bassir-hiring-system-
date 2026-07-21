<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not Found · Bassir AI Recruitment</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f8fb;font-family:Inter,Arial,sans-serif;color:#14213d}
        .panel{max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:32px;box-shadow:0 18px 50px rgba(20,33,61,.08)}
        h1{margin:0 0 10px;font-size:26px}.muted{color:#64748b}.code{font-weight:800;color:#0f766e}.btn{display:inline-block;margin-top:20px;border-radius:7px;background:#0f766e;color:#fff;text-decoration:none;font-weight:750;padding:10px 14px}
    </style>
</head>
<body>
    <main class="panel">
        <div class="code">404</div>
        <h1>Bassir AI Recruitment</h1>
        <p class="muted">The page you requested was not found. Use the dashboard navigation to continue.</p>
        <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
    </main>
</body>
</html>
