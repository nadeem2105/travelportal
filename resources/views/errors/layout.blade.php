<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · {{ config('app.name', 'Leemroz Travels') }}</title>
    {{-- Self-contained styles: error pages must render even if assets/DB are unavailable. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; color: #0f172a;
               background: radial-gradient(1200px 600px at 50% -10%, #eff6ff 0%, #f6fafd 55%, #f6fafd 100%); padding: 24px; }
        .card { max-width: 520px; width: 100%; text-align: center; background: #fff; border-radius: 24px;
                box-shadow: 0 24px 60px rgba(15,23,42,.10); padding: 48px 32px; }
        .code { font-size: 64px; font-weight: 800; line-height: 1; color: #2563eb; letter-spacing: -.02em; }
        h1 { font-size: 22px; font-weight: 700; margin: 16px 0 8px; }
        p { color: #64748b; font-size: 14px; margin: 0 auto 24px; max-width: 380px; line-height: 1.6; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a.btn { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; font-weight: 600;
                font-size: 14px; padding: 11px 22px; border-radius: 999px; }
        a.primary { background: #2563eb; color: #fff; }
        a.primary:hover { background: #1d4ed8; }
        a.ghost { background: #f1f5f9; color: #334155; }
        a.ghost:hover { background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="btn primary" href="{{ url('/') }}">Back to Home</a>
            <a class="btn ghost" href="{{ url('/packages') }}">Browse Packages</a>
        </div>
    </div>
</body>
</html>
