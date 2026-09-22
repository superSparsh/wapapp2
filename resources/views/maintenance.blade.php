<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance — {{ $appName }}</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicons/favicon-32.png') }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}">
  <style>
    :root {
      --bg1: #0b1220;
      --bg2: #12263f;
      --accent: #22c55e;
      --text: #f8fafc;
      --muted: #94a3b8;
      --card: rgba(15, 23, 42, 0.72);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: "Poppins", "Segoe UI", sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(34, 197, 94, 0.25), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(56, 189, 248, 0.18), transparent 55%),
        linear-gradient(160deg, var(--bg1), var(--bg2));
      display: grid;
      place-items: center;
      padding: 24px;
    }
    .card {
      width: min(560px, 100%);
      background: var(--card);
      border: 1px solid rgba(148, 163, 184, 0.25);
      border-radius: 24px;
      padding: 36px 32px;
      backdrop-filter: blur(14px);
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
      text-align: center;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 999px;
      padding: 8px 14px;
      background: rgba(34, 197, 94, 0.12);
      color: #86efac;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--accent);
      box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.15);
      animation: pulse 1.6s ease-in-out infinite;
    }
    h1 {
      margin: 20px 0 10px;
      font-size: clamp(1.6rem, 3vw, 2rem);
      line-height: 1.2;
    }
    p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
      font-size: 0.95rem;
      white-space: pre-line;
    }
    .until {
      margin-top: 18px;
      display: inline-block;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(148, 163, 184, 0.1);
      color: #cbd5e1;
      font-size: 0.85rem;
    }
    .foot {
      margin-top: 28px;
      font-size: 0.8rem;
      color: #64748b;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.15); opacity: 0.7; }
    }
  </style>
</head>
<body>
  <main class="card" role="status" aria-live="polite">
    <div class="badge"><span class="dot" aria-hidden="true"></span> Under maintenance</div>
    <h1>We'll be right back</h1>
    <p>{{ filled($message) ? $message : "We're making a few improvements. Thanks for your patience — this won't take long." }}</p>
    @if (! empty($until))
      <div class="until">Expected back by {{ \Illuminate\Support\Carbon::parse($until)->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</div>
    @endif
    <div class="foot">Your dashboard is temporarily paused. WhatsApp automations may continue running in the background.</div>
  </main>
</body>
</html>
