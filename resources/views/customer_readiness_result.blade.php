<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Readiness Result</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #eef4ef;
      --surface: #fff;
      --text: #1b3a2c;
      --text-muted: #60786a;
      --primary: #14934f;
      --border: #d9e6de;
      --radius: 18px;
      --shadow: 0 12px 28px rgba(17, 58, 35, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'DM Sans', sans-serif;
      background: linear-gradient(180deg, #f5faf7 0%, var(--bg) 100%);
      color: var(--text);
      min-height: 100vh;
    }
    .header {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--surface);
    }
    .header-logo { width: 34px; height: 34px; border-radius: 8px; }
    .header-title { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.3rem; line-height: 1; color: var(--primary); }
    .header-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 2px; }
    .header-badge {
      margin-left: auto;
      border: 1px solid #b7dec6;
      background: #ecfbf2;
      color: #117742;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 7px 12px;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .wrap { max-width: 860px; margin: 40px auto; padding: 0 16px; }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 30px;
    }
    .ok-icon {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #e7f8ef;
      border: 1px solid #bfe9d0;
      color: #0e7a41;
      font-size: 1.6rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 12px;
    }
    h1 { margin: 0 0 8px; font-family: 'Inter', sans-serif; font-size: 1.9rem; line-height: 1.2; }
    p { margin: 0; color: var(--text-muted); line-height: 1.6; }
    .row { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
    .btn {
      border: none;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-secondary { background: #f3f8f5; color: var(--text); border: 1px solid var(--border); }
    .note {
      margin-top: 18px;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #f6fbf8;
      color: #446556;
      font-size: 0.9rem;
    }
    @media (max-width: 700px) {
      .header { padding: 14px 14px; }
      .wrap { margin: 24px auto; }
      .card { padding: 20px; }
      h1 { font-size: 1.45rem; }
      .row .btn { width: 100%; }
    }
  </style>
</head>
<body>
  <header class="header">
  <a href="https://wapapp.tittu.in" target="_blank" rel="noopener">
    <img src="https://wapapp.tittu.in/images/tittu-logo.jpeg" alt="Tittu Logo" class="header-logo" onerror="this.style.display='none'" />
    </a>
    <div>
      <div class="header-title">WAPAPP</div>
      <div class="header-sub">by Tittu · Customer Readiness Check</div>
    </div>
    <div class="header-badge">Pre-Onboarding</div>
  </header>

  <div class="wrap">
    <section class="card">
      <div class="ok-icon">{{ !empty($result['eligible']) ? '✅' : '⚠️' }}</div>
      <h1>{{ $result['title'] ?? 'Thank you!' }}</h1>
      <p>{{ $result['message'] ?? 'We have received your submission.' }}</p>

      <div class="row">
        <a class="btn btn-primary" href="{{ $result['link'] ?? route('customer.readiness') }}" target="_blank" rel="noopener">
          {{ $result['link_text'] ?? 'Open next step' }}
        </a>
      </div>

      {{-- @if(!empty($result['eligible']) && !empty($result['business_email']) && !empty($result['login_url']))
        <div class="note" style="margin-top: 18px;">
          <p style="margin:0 0 10px;">
            <strong>Business Email:</strong> {{ $result['business_email'] }}
          </p>
          <p style="margin:0;">
            <strong>Login Link:</strong>
            <a href="{{ $result['login_url'] }}" style="color: inherit; text-decoration: underline;">{{ $result['login_url'] }}</a>
          </p>
          @if(!empty($result['temp_password']))
            <p style="margin:10px 0 0;">
              <strong>Temporary Password:</strong> {{ $result['temp_password'] }}
            </p>
          @endif
        </div>
      @endif --}}

      <div class="note">
        A confirmation has been processed on the backend for your submission. 
      </div>
    </section>
  </div>

  <script>
    // On thank-you page, clear draft so the form is reset when user returns.
    try {
      localStorage.removeItem('wapapp_draft');
    } catch (e) {}
  </script>
</body>
</html>
