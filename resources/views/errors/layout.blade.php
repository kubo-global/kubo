<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>KUBO &middot; @yield('title')</title>
  {{-- Self-contained on purpose: error pages must render even when assets or the DB are down. --}}
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:linear-gradient(135deg,#4a619c 0%,#34486f 55%,#25304c 100%);color:#fff}
    .card{max-width:30rem;text-align:center}
    svg{height:3.25rem;width:auto;margin:0 auto 1.25rem;display:block;fill:#fff;opacity:.95}
    .code{font-size:4.5rem;font-weight:800;line-height:1;color:rgba(255,255,255,.85);letter-spacing:-.02em}
    h1{margin-top:.4rem;font-size:1.5rem;font-weight:700}
    p{margin-top:.6rem;color:#cdd6ea;line-height:1.5}
    a.btn{display:inline-block;margin-top:1.6rem;padding:.65rem 1.4rem;background:#fff;color:#34486f;font-weight:600;border-radius:.55rem;text-decoration:none;transition:background .15s}
    a.btn:hover{background:#eef1f8}
  </style>
</head>
<body>
  <div class="card">
    <svg viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg"><path d="M228.39 76.13l11.24-6.48L125.94 4 1 76.13 38.9 98v87.53l87 50.24 87-50.24V85l-87 50.26-75.77-43.73 64.57-37.28-11.24-6.48L38.9 85l-15.43-8.87L125.94 17l102.45 59.13zM50.14 104.49l75.8 43.84 75.79-43.84V179l-75.79 43.83L50.14 179v-74.51z"/></svg>
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    @hasSection('hideCta')
    @else
      <a class="btn" href="{{ url('/') }}">@yield('cta', 'Back to KUBO')</a>
    @endif
  </div>
</body>
</html>
