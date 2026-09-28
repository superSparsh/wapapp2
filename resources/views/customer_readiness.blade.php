<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Customer Readiness — WAPAPP by Tittu</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet"/>
<style>
  :root {
    --bg: #f0f4f0;
    --surface: #ffffff;
    --surface2: #f8faf8;
    --border: #d6e4d6;
    --border-focus: #25a560;
    --primary: #128b4a;
    --primary-light: #e8f5ee;
    --primary-glow: rgba(18,139,74,0.12);
    --accent: #06c167;
    --warn: #e07b00;
    --warn-bg: #fff8ee;
    --warn-border: #f5c36a;
    --error: #c0392b;
    --error-bg: #fdf1f0;
    --error-border: #f1a9a4;
    --success: #128b4a;
    --success-bg: #e8f5ee;
    --success-border: #86d4a8;
    --text: #111a16;
    --text-muted: #6b7a71;
    --text-light: #9aaa9f;
    --radius: 14px;
    --radius-sm: 8px;
    --shadow: 0 2px 24px rgba(0,0,0,0.07);
    --shadow-md: 0 8px 40px rgba(0,0,0,0.10);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    background-image:
      radial-gradient(circle at 10% 20%, rgba(18,139,74,0.06) 0%, transparent 50%),
      radial-gradient(circle at 90% 80%, rgba(6,193,103,0.05) 0%, transparent 50%);
  }

  /* ── Header ── */
  .header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 18px 32px;
    display: flex;
    align-items: center;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 12px rgba(0,0,0,0.06);
  }
  .header-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: contain;
    background: #f0f4f0;
    padding: 4px;
  }
  .header-title {
    font-family: 'Inter', sans-serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
  }
  .header-sub {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 400;
  }
  .header-badge {
    margin-left: auto;
    background: var(--primary-light);
    color: var(--primary);
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid var(--success-border);
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  /* ── Progress bar ── */
  .progress-bar-wrap {
    background: var(--border);
    height: 4px;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 2px;
  }
  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 2px;
    transition: width 0.4s ease;
  }

  /* ── Layout ── */
  .layout {
    max-width: 840px;
    margin: 0 auto;
    padding: 40px 20px 80px;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .page-intro {
    text-align: center;
    padding: 8px 0 4px;
  }
  .page-intro h1 {
    font-family: 'Inter', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 6px;
    letter-spacing: 1px;
  }
  .page-intro p {
    color: var(--text-muted);
    font-size: 0.95rem;
  }

  /* ── Progress steps ── */
  .steps-nav {
    display: flex;
    gap: 4px;
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 8px;
    overflow: hidden;
  }
  .step-btn {
    flex: 1 1 0;
    min-width: 0;
    padding: 10px 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-muted);
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-height: 62px;
    text-align: center;
  }
  .step-btn.active { background: var(--primary); color: #fff; }
  .step-btn.done { background: var(--success-bg); color: var(--primary); }
  .step-btn:hover:not(.active) { background: var(--bg); }
  .step-num {
    font-family: 'Inter', sans-serif;
    font-weight: 700;
    font-size: 0.8rem;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    line-height: 1;
    flex-shrink: 0;
  }
  .step-btn.active .step-num { background: rgba(255,255,255,0.25); }
  .step-btn.done .step-num { background: var(--success-border); }

  /* ── Section card ── */
  .section-card {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    display: none;
  }
  .section-card.active { display: block; }

  .section-header {
    padding: 22px 28px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .section-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    background: var(--primary-light);
    flex-shrink: 0;
  }
  .section-header h2 {
    font-family: 'Inter', sans-serif;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
  }
  .section-header p {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-top: 2px;
  }

  .section-body {
    padding: 24px 28px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  /* ── Field groups ── */
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media(max-width:600px) { .field-row { grid-template-columns: 1fr; } }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .field label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .field label .req { color: var(--error); }
  .field label .badge {
    font-size: 0.68rem;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 500;
    background: var(--warn-bg);
    color: var(--warn);
    border: 1px solid var(--warn-border);
    margin-left: 4px;
  }

  .field input, .field select {
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    color: var(--text);
    background: var(--surface2);
    transition: all 0.2s;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
  }
  .field select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7a71' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
    cursor: pointer;
  }
  .field input:focus, .field select:focus {
    border-color: var(--border-focus);
    background: var(--surface);
    box-shadow: 0 0 0 3px var(--primary-glow);
  }
  .field input.valid { border-color: var(--success-border); }
  .field input.invalid, .field select.invalid { border-color: var(--error-border); background: var(--error-bg); }
  .field input.warn, .field select.warn { border-color: var(--warn-border); background: var(--warn-bg); }

  .field-hint {
    font-size: 0.76rem;
    color: var(--text-muted);
    display: flex;
    align-items: flex-start;
    gap: 5px;
  }
  .field-error {
    font-size: 0.76rem;
    color: var(--error);
    display: none;
    align-items: center;
    gap: 5px;
  }
  .field-error.show { display: flex; }
  .field-warn-msg {
    font-size: 0.76rem;
    color: var(--warn);
    display: none;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    background: var(--warn-bg);
    border: 1px solid var(--warn-border);
    border-radius: var(--radius-sm);
  }
  .field-warn-msg.show { display: flex; }
  .field-block-msg {
    font-size: 0.76rem;
    color: var(--error);
    display: none;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    background: var(--error-bg);
    border: 1px solid var(--error-border);
    border-radius: var(--radius-sm);
  }
  .field-block-msg.show { display: flex; }

  /* ── Info callout ── */
  .callout {
    padding: 14px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid;
    font-size: 0.82rem;
    display: flex;
    gap: 10px;
    line-height: 1.55;
  }
  .callout.info { background: #eff7ff; border-color: #b0d0f5; color: #1a4a80; }
  .callout.warn { background: var(--warn-bg); border-color: var(--warn-border); color: #7a4800; }
  .callout.block { background: var(--error-bg); border-color: var(--error-border); color: #7a1a10; }
  .callout.success { background: var(--success-bg); border-color: var(--success-border); color: #0e5c30; }
  .callout-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

  /* ── Nav buttons ── */
  .nav-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 28px;
    border-top: 1px solid var(--border);
    background: var(--surface2);
  }
  .btn {
    padding: 11px 22px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 7px;
  }
  .btn-primary {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 2px 12px rgba(18,139,74,0.25);
  }
  .btn-primary:hover { background: #0e7040; box-shadow: 0 4px 20px rgba(18,139,74,0.35); }
  .btn-secondary {
    background: transparent;
    color: var(--text-muted);
    border: 1.5px solid var(--border);
  }
  .btn-secondary:hover { background: var(--bg); color: var(--text); }
  .btn-ghost {
    background: transparent;
    color: var(--text-muted);
    font-size: 0.82rem;
    padding: 8px 14px;
  }

  /* ── Summary card ── */
  .summary-card {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    display: none;
  }
  .summary-card.active { display: block; }

  .summary-header {
    padding: 24px 28px 18px;
    border-bottom: 1px solid var(--border);
  }
  .summary-header h2 {
    font-family: 'Inter', sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--text);
  }
  .summary-header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }

  .score-ring-wrap {
    display: flex;
    align-items: center;
    gap: 28px;
    padding: 24px 28px;
    border-bottom: 1px solid var(--border);
  }
  .score-ring {
    flex-shrink: 0;
    position: relative;
    width: 112px;
    height: 112px;
  }
  .score-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
  .score-ring-label {
    position: absolute;
    inset: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 2px;
    border-radius: 50%;
    overflow: hidden;
  }
  .score-pct {
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    white-space: nowrap;
  }
  .score-sub {
    font-size: 0.58rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    line-height: 1;
  }

  .score-stats { display: flex; flex-direction: column; gap: 8px; }
  .stat-row { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
  .stat-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
  }
  .dot-pass { background: var(--accent); }
  .dot-warn { background: var(--warn); }
  .dot-fail { background: var(--error); }

  .checklist { padding: 0 28px 24px; display: flex; flex-direction: column; gap: 10px;margin-top: 20px; }
  .check-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    font-size: 0.84rem;
  }
  .check-item.pass { border-color: var(--success-border); background: var(--success-bg); }
  .check-item.warn { border-color: var(--warn-border); background: var(--warn-bg); }
  .check-item.fail { border-color: var(--error-border); background: var(--error-bg); }
  .check-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
  .check-label { font-weight: 600; color: var(--text); }
  .check-note { color: var(--text-muted); margin-top: 2px; font-size: 0.79rem; }

  /* ── Meta verification guide ── */
  .guide-section {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
  }
  .guide-toggle {
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
  }
  .guide-toggle:hover { background: var(--surface2); }
  .guide-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text);
  }
  .guide-chevron { transition: transform 0.3s; font-size: 1rem; color: var(--text-muted); }
  .guide-chevron.open { transform: rotate(180deg); }

  .guide-body {
    display: none;
    padding: 0 24px 24px;
    border-top: 1px solid var(--border);
  }
  .guide-body.open { display: block; }

  .guide-steps { display: flex; flex-direction: column; gap: 0; padding-top: 20px; }
  .guide-step {
    display: flex;
    gap: 16px;
    position: relative;
    padding-bottom: 24px;
  }
  .guide-step:last-child { padding-bottom: 0; }
  .step-line-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    flex-shrink: 0;
  }
  .step-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', sans-serif;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    z-index: 1;
    box-shadow: 0 2px 8px rgba(18,139,74,0.25);
  }
  .step-connector {
    width: 2px;
    flex: 1;
    background: var(--border);
    margin-top: 4px;
  }
  .guide-step:last-child .step-connector { display: none; }

  .guide-step-content { padding-top: 6px; }
  .guide-step-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text);
    margin-bottom: 6px;
  }
  .guide-step-body {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.6;
  }
  .guide-step-body ul { padding-left: 16px; margin-top: 6px; }
  .guide-step-body ul li { margin-bottom: 4px; }
  .guide-step-body a { color: var(--primary); text-decoration: none; }
  .guide-step-body a:hover { text-decoration: underline; }
  .guide-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--success-border);
    margin-left: 6px;
  }

  /* ── Submit section ── */
  .submit-section {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    display: none;
  }
  .submit-section.active { display: flex; }
  .submit-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px;
    align-items: end;
    justify-content: end; }
  .btn-submit {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: #fff;
    padding: 14px 32px;
    font-size: 1rem;
    box-shadow: 0 4px 20px rgba(18,139,74,0.35);
  }
  .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(18,139,74,0.45); }
  .btn-save { border-color: var(--border); color: var(--text-muted); }

  /* ── Pill progress ── */
  .top-progress {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .top-progress-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
  .top-progress-pct {
    font-family: 'Inter', sans-serif;
    font-weight: 800;
    font-size: 1rem;
    color: var(--primary);
    white-space: nowrap;
    min-width: 40px;
    text-align: right;
  }

  /* ── Toast ── */
  .toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #1a2e22;
    color: #fff;
    padding: 12px 22px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 500;
    box-shadow: 0 4px 24px rgba(0,0,0,0.2);
    transition: transform 0.3s ease;
    z-index: 999;
    white-space: nowrap;
  }
  .toast.show { transform: translateX(-50%) translateY(0); }

  /* ── Modal ───────────────────────────────────── */
  .modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.52);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .modal {
    width: 100%;
    max-width: 560px;
    background: var(--surface);
    border-radius: 14px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    padding: 20px;
  }
  .modal h3 {
    font-family: 'Syne', sans-serif;
    font-size: 1.05rem;
    margin-bottom: 8px;
    color: var(--text);
  }
  .help-preview-card {
    margin-top: 14px;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface2);
  }
  .help-preview-card p {
    margin: 0 0 10px;
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.55;
  }
  .help-preview-thumb {
    display: block;
    width: 100%;
    max-width: 320px;
    border-radius: 10px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    cursor: zoom-in;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .help-preview-thumb:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
  }
  .image-lightbox {
    max-width: min(92vw, 980px);
    background: transparent;
    border: 0;
    box-shadow: none;
    padding: 0;
  }
  .image-lightbox img {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 14px;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.35);
  }

  /* ── Responsive ── */
  @media(max-width:500px) {
    .header { padding: 14px 16px; }
    .layout { padding: 24px 12px 60px; }
    .section-body, .section-header, .nav-row { padding-left: 16px; padding-right: 16px; }
    .score-ring-wrap { flex-direction: column; align-items: flex-start; }
    .score-ring { width: 96px; height: 96px; }
    .score-ring-label { inset: 12px; }
    .score-pct { font-size: 1.32rem; }
    .score-sub { font-size: 0.52rem; }
    .steps-nav { gap: 6px; padding: 6px; display: grid; grid-template-columns: 1fr 1fr; }
    .step-btn { font-size: 0.7rem; padding: 8px 6px; min-height: 56px; }
    .step-num { width: 24px; height: 24px; font-size: 0.72rem; }
  }
</style>
</head>
<body>

<!-- Header -->
<header class="header">
  <img src="https://wapapp.tittu.in/images/tittu-logo.jpeg" alt="Tittu Logo" class="header-logo" onerror="this.style.display='none'"/>
  <div>
    <div class="header-title">WAPAPP</div>
    <div class="header-sub">by Tittu · Customer Readiness Check</div>
  </div>
  <div class="header-badge">Pre-Demo Readiness Check</div>
</header>

<div class="layout">

  @if (session('success'))
    <div class="callout success" style="margin-bottom:12px;">
      <span class="callout-icon">✅</span>
      <span>{{ session('success') }}</span>
    </div>
  @endif
  @if (session('error'))
    <div class="callout block" style="margin-bottom:12px;">
      <span class="callout-icon">🚫</span>
      <span>{{ session('error') }}</span>
    </div>
  @endif
  @if ($errors->any())
    <div class="callout block" style="margin-bottom:12px;display:block;">
      <span class="callout-icon">⚠️</span>
      <div style="margin-top:4px;">
        <div style="font-weight:700;">Please fix these errors and submit again:</div>
        <ul style="margin:8px 0 0 18px; padding:0;">
          @foreach ($errors->all() as $error)
            <li style="margin:2px 0;">{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <!-- Intro -->
  <div class="page-intro">
    <h1>Let's check if you're demo-ready</h1>
    <p>We've received your enquiry and we're excited to show you WAPAPP. Before we schedule your demo, this quick readiness check helps us confirm that the essentials are in place — so when we meet, we can focus entirely on showing you what the platform can do for your business..</p>
  </div>

  <!-- Top progress -->
  <div class="top-progress">
    <div class="top-progress-label">Overall readiness</div>
    <div class="progress-bar-wrap" style="flex:1;">
      <div class="progress-bar-fill" id="topProgressBar" style="width:0%"></div>
    </div>
    <div class="top-progress-pct" id="topProgressPct">0%</div>
  </div>

  <!-- Step nav -->
  <div class="steps-nav">
    <button class="step-btn active" onclick="goTo(0)">
      <div class="step-num">1</div>
      <span>Your Details</span>
    </button>
    <button class="step-btn" onclick="goTo(1)">
      <div class="step-num">2</div>
      <span>Business Docs</span>
    </button>
    <button class="step-btn" onclick="goTo(2)">
      <div class="step-num">3</div>
      <span>Meta Access</span>
    </button>
    <button class="step-btn" onclick="goTo(3)">
      <div class="step-num">4</div>
      <span>Your Number</span>
    </button>
    <button class="step-btn" onclick="goTo(4)">
      <div class="step-num">5</div>
      <span>Readiness Report</span>
    </button>
  </div>

  <!-- ────────────────────────── SECTION 1: Customer Details ──────────────────────────── -->
  <div class="section-card active" id="sec-0">
    <div class="section-header">
      <div class="section-icon">👤</div>
      <div>
        <h2>Tell us about yourself</h2>
        <p>A few quick details so we know who we're speaking with.</p>
      </div>
    </div>
    <div class="section-body">

      <div class="field-row">
        <div class="field">
          <label>Customer name <span class="req">*</span></label>
          <input type="text" id="f-name" placeholder="Your full name" oninput="validateField('f-name')" onblur="validateField('f-name')"/>
          <div class="field-error" id="e-name">⚠ Please enter your full name (at least 3 characters).</div>
        </div>
        <div class="field">
          <label>Customer email <span class="req">*</span></label>
          <input type="email" id="f-email" placeholder="you@company.com" oninput="validateField('f-email')" onblur="validateField('f-email')"/>
          <div class="field-error" id="e-email">⚠ Enter a valid email address.</div>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Business name <span class="req">*</span></label>
          <input type="text" id="f-bname" placeholder="e.g. Tekkonnect Pvt Ltd" oninput="validateField('f-bname')" onblur="validateField('f-bname')"/>
          <div class="field-error" id="e-bname">⚠ Enter your registered business name.</div>
        </div>
        <div class="field">
          <label>Business email <span class="req">*</span> <span class="badge">Official domain required</span></label>
          <input type="email" id="f-bemail" placeholder="admin@yourdomain.com" oninput="validateField('f-bemail')" onblur="validateField('f-bemail')"/>
          <div class="field-hint">💡 Must use your company domain — not Gmail, Yahoo, Outlook etc.</div>
          <div class="field-hint" id="business-email-live-status">📧 Business email status: not checked yet.</div>
          <div class="field-error" id="e-bemail" style="display:none;">⚠ Enter a valid business email.</div>
        </div>
      </div>

      <div class="field">
        <label>Website URL <span class="req">*</span></label>
        <input type="url" id="f-website" placeholder="https://yourbusiness.com" oninput="validateField('f-website')" onblur="validateField('f-website')"/>
        <div class="field-hint">🔒 Must be a live, HTTPS-secured website. Meta will verify this during Business Verification.</div>
        <div class="field-hint" id="website-live-status">🌐 Website status: not checked yet.</div>
        <div class="field-error" id="e-website">⚠ URL must start with https:// and be a valid web address.</div>
        <div class="field-block-msg" id="b-website">
          <span>🚫</span>
          <span><strong>HTTP websites are not accepted by Meta.</strong> You must have SSL (https://) enabled before onboarding can proceed. Contact your hosting provider to get a free SSL certificate via Let's Encrypt.</span>
        </div>
      </div>

    </div>
    <div class="nav-row">
      <span></span>
      <button class="btn btn-primary" onclick="nextSection(0)">Continue →</button>
    </div>
  </div>

  <!-- ────────────────────────── SECTION 2: Business Identity ──────────────────────────── -->
  <div class="section-card" id="sec-1">
    <div class="section-header">
      <div class="section-icon">📄</div>
      <div>
        <h2>Your business documents</h2>
        <p>Meta verifies every business before activating the WhatsApp. Let's confirm your documents are ready.</p>
      </div>
    </div>
    <div class="section-body">

      <div class="callout info">
        <span class="callout-icon">ℹ️</span>
        <span>Meta's Business Verification requires you to upload official documents. Having these ready speeds up the process significantly. Verification can take 3–7 business days.</span>
      </div>

      <div class="">
        <div class="field">
          <label>Registration document <span class="req">*</span></label>
          <select id="f-regdoc" onchange="validateField('f-regdoc')">
            <option value="">Select document type</option>
            <option value="gst">GST certificate</option>
            <option value="udyam">Udyam MSME certificate</option>
          </select>
          <div class="field-error" id="e-regdoc">⚠ Please select a document type.</div>
        </div>

        {{-- Upload registration PDF — removed per request (no file upload on this form).
        <div class="field">
          <label>Upload registration PDF <span class="req">*</span></label>
          <input type="file" id="f-regdoc-file" accept="application/pdf,.pdf" onchange="validateField('f-regdoc-file')">
          <div class="field-hint">📎 Upload the PDF of your GST or Udyam certificate. Only PDF files are accepted here.</div>
          <div class="field-error" id="e-regdoc-file">⚠ Please upload a valid PDF file (max 1 file).</div>
        </div>
        --}}
      </div>

      {{-- <div class="callout info">
        <span class="callout-icon">📋</span>
        <span><strong>Document checklist for Meta verification:</strong> Ensure the PDF clearly shows your business name and registration number, and that it matches the name on your Meta Business Manager.</span>
      </div> --}}

    </div>
    <div class="nav-row">
      <button class="btn btn-secondary" onclick="prevSection(1)">← Back</button>
      <button class="btn btn-primary" onclick="nextSection(1)">Continue →</button>
    </div>
  </div>

  <!-- ────────────────────────── SECTION 3: Meta & Facebook ──────────────────────────── -->
  <div class="section-card" id="sec-2">
    <div class="section-header">
      <div class="section-icon">🔷</div>
      <div>
        <h2>Meta and Facebook access</h2>
        <p>WhatsApp integration requires the right access levels on Meta. Let's make sure you're set.</p>
      </div>
    </div>
    <div class="section-body">

      <div class="callout info">
        <span class="callout-icon">🔑</span>
        <span>You must have <strong>Admin access</strong> on your Meta Business Manager account to complete the WhatsApp integration. Employee-level access will not work for the setup process.</span>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Meta Business Manager admin access <span class="req">*</span></label>
          <select id="f-metaadmin" onchange="validateField('f-metaadmin')">
            <option value="">Select your access level</option>
            <option value="admin">I have admin access ✓</option>
            <option value="employee">I have employee access only</option>
            <option value="none">No access / No account</option>
          </select>
          <div class="field-error" id="e-metaadmin">⚠ Please select your Meta Business Manager access level.</div>
          <div class="field-warn-msg" id="w-metaadmin" style="display:none">
            ⚠ <span>You only have employee access. You will need to ask the admin of your Meta Business Manager to either grant you Admin rights or complete the WhatsApp setup on your behalf.</span>
          </div>
          <div class="field-block-msg" id="b-metaadmin" style="display:none">
            <span>🚫</span>
            <span><strong>No Meta Business Manager account.</strong> You must create one at <a href="https://business.facebook.com" target="_blank">business.facebook.com</a> before onboarding can begin. This is mandatory by Meta.</span>
          </div>
        </div>

        <div class="field">
          <label>Two-Factor Authentication (MFA) on Meta admins <span class="req">*</span></label>
          <select id="f-mfa" onchange="validateField('f-mfa')">
            <option value="">Select MFA status</option>
            <option value="all">Enabled for all admins ✓</option>
            <option value="some">Enabled for some admins</option>
            <option value="none">Not enabled</option>
          </select>
          <div class="field-error" id="e-mfa">⚠ Please select the MFA status.</div>
          <div class="field-warn-msg" id="w-mfa" style="display:none">
            ⚠ <span>MFA is not enabled for all admins. Meta strongly recommends MFA for all admins and may require it for sensitive operations. Enable it now at <strong>Business Settings → Security Center</strong>.</span>
          </div>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Personal Facebook account <span class="req">*</span></label>
          <select id="f-fbaccount" onchange="validateField('f-fbaccount')">
            <option value="">Select option</option>
            <option value="yes">Available ✓</option>
            <option value="no">Not available</option>
          </select>
          <div class="field-error" id="e-fbaccount">⚠ Please select an option.</div>
          <div class="field-block-msg" id="b-fbaccount" style="display:none">
            <span>🚫</span>
            <span><strong>A personal Facebook account is required.</strong> Meta Business Manager must be linked to a personal Facebook account. You need to create one at <a href="https://www.facebook.com" target="_blank">facebook.com</a> before proceeding.</span>
          </div>
        </div>

        <div class="field">
          <label>Meta Business account status</label>
          <select id="f-fbpage" onchange="validateField('f-fbpage')">
            <option value="">Select option</option>
            <option value="yes">Yes, Meta Business is created ✓</option>
            <option value="no">Not yet created</option>
            <option value="unsure">Not sure</option>
          </select>
          <div class="field-error" id="e-fbpage">⚠ Please select a valid option.</div>
          <div class="field-warn-msg" id="w-fbpage" style="display:none">
            ⚠ <span>A Meta Business account is required to proceed. You can create or access it at <a href="https://business.facebook.com" target="_blank">business.facebook.com</a>.</span>
          </div>
        </div>
      </div>

      <div class="field" id="fbpagename-field">
        <label for="f-fbpagename">Meta Business portfolio URL</label>
        <div class="field-hint">
          <button type="button" class="btn-ghost" style="padding:0;border:0" onclick="openBusinessPortfolioInfo()">How to get this Business portfolio URL?</button>
        </div>
        <input type="url" id="f-fbpagename"
               placeholder="https://business.facebook.com/latest/settings/business_info"
               oninput="validateField('f-fbpagename')" onblur="validateField('f-fbpagename')"/>
        <div class="field-hint" id="fbpage-live-status"></div>
        <div class="field-error" id="e-fbpagename">⚠ Please enter a valid Meta Business portfolio URL.</div>
      </div>

    </div>
    <div class="nav-row">
      <button class="btn btn-secondary" onclick="prevSection(2)">← Back</button>
      <button class="btn btn-primary" onclick="nextSection(2)">Continue →</button>
    </div>
  </div>

  <!-- ────────────────────────── SECTION 4: WhatsApp Number ──────────────────────────── -->
  <div class="section-card" id="sec-3">
    <div class="section-header">
      <div class="section-icon">📱</div>
      <div>
        <h2>Your WhatsApp number</h2>
        <p>Your WhatsApp number is the foundation of your WhatsApp setup. A few important things to confirm here.</p>
      </div>
    </div>
    <div class="section-body">

      <div class="callout warn">
        <span class="callout-icon">⚠️</span>
        <span><strong>Important:</strong> A number currently active on WhatsApp (personal or Business App) cannot be used simultaneously. Migrating will disconnect it from the app.</span>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Phone number plan <span class="req">*</span></label>
          <select id="f-numplan" onchange="validateField('f-numplan')">
            <option value="">Select your plan</option>
            <option value="new">New number — never used on WhatsApp ✓</option>
            <option value="migrate">Existing number — will migrate to WhatsApp</option>
            <option value="apponly">Existing app number — cannot migrate</option>
            <option value="undecided">Not decided yet</option>
          </select>
          <div class="field-error" id="e-numplan">⚠ Please select your number plan.</div>
          <div class="field-block-msg" id="b-numplan" style="display:none">
            <span>🚫</span>
            <span><strong>This number cannot be onboarded.</strong> If you want to use a number already on the WhatsApp App without migrating, you must get a new number dedicated for WhatsApp use.</span>
          </div>
          <div class="field-warn-msg" id="w-numplan" style="display:none">
            ⚠ <span>You haven't decided on a number yet. You'll need to confirm a number before onboarding can be completed. We recommend getting a new dedicated number for WhatsApp.</span>
          </div>
          <div class="field-warn-msg" id="wm-numplan" style="display:none">
            ⚠ <span>Migration note: When you migrate your existing number to WhatsApp, your WhatsApp App on that number will stop working. Ensure your team is prepared for this transition.</span>
          </div>
        </div>

        <div class="field">
          <label>OTP reachability <span class="req">*</span></label>
          <select id="f-otp" onchange="validateField('f-otp')">
            <option value="">Select option</option>
            <option value="yes">Can receive SMS or voice OTP ✓</option>
            <option value="no">Cannot receive OTP</option>
          </select>
          <div class="field-error" id="e-otp">⚠ Please select an option.</div>
          <div class="field-block-msg" id="b-otp" style="display:none">
            <span>🚫</span>
            <span><strong>OTP is mandatory for verification.</strong> Meta sends an OTP to verify number ownership. Without the ability to receive SMS or voice calls, the onboarding process cannot be completed. Please use a number with active SMS/call capability.</span>
          </div>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Number type <span class="req">*</span></label>
          <select id="f-numtype" onchange="validateField('f-numtype')">
            <option value="">Select number type</option>
            <option value="physical">Physical SIM / Landline ✓</option>
            <option value="voip">Virtual / VoIP number</option>
          </select>
          <div class="field-error" id="e-numtype">⚠ Please select number type.</div>
          <div class="field-block-msg" id="b-numtype" style="display:none">
            <span>🚫</span>
            <span><strong>Virtual/VoIP numbers are not accepted by Meta.</strong> Meta prohibits VoIP numbers (e.g. Google Voice, Twilio virtual numbers) for WhatsApp. You must use a physical SIM or a real landline number.</span>
          </div>
        </div>

        <div class="field">
          <label>WhatsApp number to use</label>
          <input type="tel" id="f-wanum" placeholder="+91 98765 43210" oninput="validateField('f-wanum')" onblur="validateField('f-wanum')"/>
          <div class="field-hint">💡 Include country code. e.g. +91 for India.</div>
          <div class="field-error" id="e-wanum">⚠ Enter a valid phone number with country code.</div>
        </div>
      </div>

      <div class="field">
        <label for="f-wadisplayname">
          WhatsApp Display Name <span class="req">*</span>
          <button type="button" class="btn-ghost" style="margin-left:4px; padding-left:0;border:0"
                  onclick="openDisplayNameInfo()">ⓘ What is this?</button>
        </label>
        <input type="text" id="f-wadisplayname" placeholder="e.g. Your Brand Name" maxlength="75" autocomplete="organization" oninput="validateField('f-wadisplayname')" onblur="validateField('f-wadisplayname')"/>
        <div class="field-hint">
          This is the brand name customers see on WhatsApp. Keep it short, match your legal/known brand, and avoid slogans or extra symbols.
          <a href="https://www.facebook.com/business/help/757569725593362" target="_blank" rel="noopener noreferrer">See full Meta rules</a>.
        </div>
        <div class="field-error" id="e-wadisplayname">⚠ Enter the display name you want to use on WhatsApp.</div>
      </div>

    </div>
    <div class="nav-row">
      <button class="btn btn-secondary" onclick="prevSection(3)">← Back</button>
      <button class="btn btn-primary" onclick="nextSection(3)">View Summary →</button>
    </div>
  </div>

  <!-- ────────────────────────── SECTION 5: Summary ──────────────────────────── -->
  <div class="summary-card" id="sec-4">
    <div class="summary-header">
      <h2>Your demo readiness report</h2>
      <p>Here is everything we found. This report will help us plan your demo and flag anything that needs attention before we get started.</p>
    </div>
    <div class="score-ring-wrap">
      <div class="score-ring">
        <svg viewBox="0 0 96 96">
          <circle cx="48" cy="48" r="40" fill="none" stroke="#d6e4d6" stroke-width="8"/>
          <circle cx="48" cy="48" r="40" fill="none" stroke="#128b4a" stroke-width="8"
            stroke-dasharray="251.2" stroke-dashoffset="251.2" stroke-linecap="round"
            id="scoreCircle"/>
        </svg>
        <div class="score-ring-label">
          <div class="score-pct" id="scorePct">0%</div>
          <div class="score-sub">Ready</div>
        </div>
      </div>
      <div class="score-stats">
        <div class="stat-row"><div class="stat-dot dot-pass"></div><span id="passCount">0 checks passed</span></div>
        <div class="stat-row"><div class="stat-dot dot-warn"></div><span id="warnCount">0 warnings</span></div>
        <div class="stat-row"><div class="stat-dot dot-fail"></div><span id="failCount">0 blockers</span></div>
      </div>
    </div>
    <div class="checklist" id="summaryChecklist"></div>
    <div class="nav-row">
      <button class="btn btn-secondary" onclick="prevSection(4)">← Back</button>
      <button class="btn btn-primary" onclick="goTo(0)">Edit Responses</button>
    </div>
  </div>

  <!-- Submit -->
  <div class="submit-section" id="submitSection">
    <div class="callout success">
      <span class="callout-icon">✅</span>
      <span>Your readiness check is complete. Click <strong>Submit and Request Your Demo</strong> to notify the Tittu team and begin the official WhatsApp onboarding process.</span>
    </div>
    <div class="submit-row">
          <button type="button" class="btn btn-secondary btn-save" onclick="saveDraft()">💾 Save Draft</button>
      <button type="button" class="btn btn-primary btn-submit" onclick="submitForm()">🚀 Submit and Request Your Demo</button>
    </div>
  </div>

  <!-- ────────────────────────── Meta Verification Guide ──────────────────────────── -->
  {{-- <div class="guide-section">
    <div class="guide-toggle" onclick="toggleGuide()">
      <div class="guide-title">
        <span>🔷</span>
        <span>Meta Business Verification — Step-by-Step Guide <span class="guide-chip">Required</span></span>
      </div>
      <div class="guide-chevron" id="guideChevron">▼</div>
    </div>
    <div class="guide-body" id="guideBody">
      <div style="padding: 12px 0 18px; font-size:0.84rem; color:var(--text-muted); line-height:1.6;">
        Meta Business Verification confirms that your business is real and legitimate. It is <strong>mandatory</strong> to use the WhatsApp at scale (beyond the 1,000 conversations/month limit). Here is exactly how to complete it.
      </div>
      <div class="guide-steps">

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">1</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Create or Access Meta Business Manager</div>
            <div class="guide-step-body">
              Go to <a href="https://business.facebook.com" target="_blank">business.facebook.com</a> and sign in with your personal Facebook account. If you do not have a Business Manager, click <strong>Create Account</strong> and enter your business name, your name, and your business email address.<br/>
              <ul>
                <li>Use the exact legal business name — this must match your documents.</li>
                <li>Add your business website and phone number in the settings.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">2</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Enable Two-Factor Authentication (MFA)</div>
            <div class="guide-step-body">
              Meta requires MFA for all Business Manager admins before verification can be approved.
              <ul>
                <li>Go to <strong>Business Settings → Security Center</strong>.</li>
                <li>Click <strong>Require two-factor authentication</strong> and select "All people."</li>
                <li>Every admin must complete MFA setup using an authenticator app or SMS.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">3</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Start the Business Verification Process</div>
            <div class="guide-step-body">
              In Meta Business Manager:
              <ul>
                <li>Go to <strong>Business Settings → Security Center</strong>.</li>
                <li>Click <strong>Start Verification</strong> under "Business Verification."</li>
                <li>Enter your official business name and confirm your country (India).</li>
                <li>Select your business from the suggested matches (pulled from public directories) or enter details manually if not found.</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">4</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Confirm Your Business Details</div>
            <div class="guide-step-body">
              Meta will display the details it found for your business. Confirm or correct:
              <ul>
                <li>Legal business name (must match documents exactly).</li>
                <li>Business address.</li>
                <li>Business phone number.</li>
                <li>Business website (must be live and HTTPS).</li>
              </ul>
              If Meta cannot find your business automatically, select <em>"My business is not in this list"</em> and proceed to manual document upload.
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">5</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Upload Verification Documents</div>
            <div class="guide-step-body">
              Meta will ask for one or more of the following (for India):
              <ul>
                <li><strong>Business registration:</strong> GST Certificate, or Udyam MSME Certificate.</li>
                <li><strong>Proof of address:</strong> Utility bill, bank statement, property tax receipt, or lease agreement — must match the business address.</li>
                <li><strong>Phone number verification:</strong> If your business phone is listed, Meta may verify via OTP. Otherwise, submit a document showing your business name and phone number together.</li>
              </ul>
              Ensure all documents are in PDF or JPG format, clearly legible, and not older than 3 months (for utility bills and bank statements).
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">6</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Verify Your Business Email or Phone</div>
            <div class="guide-step-body">
              After document upload, Meta will verify either your business email or phone number:
              <ul>
                <li>If verifying by email — Meta sends a code to your business domain email. Click the link to confirm.</li>
                <li>If verifying by phone — Meta sends an OTP via SMS or voice call. Enter it in the portal.</li>
              </ul>
              Ensure your business email inbox is accessible and your phone can receive calls or SMS.
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">7</div>
            <div class="step-connector"></div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">Wait for Meta Review &amp; Approval</div>
            <div class="guide-step-body">
              After submission, Meta's team manually reviews your documents. This typically takes:
              <ul>
                <li><strong>3–7 business days</strong> for standard cases.</li>
                <li>Up to <strong>2–3 weeks</strong> if documents need clarification or resubmission.</li>
              </ul>
              You will receive an email notification to the Business Manager admin email when approved or if further action is needed. You can check the status at any time under <strong>Business Settings → Security Center</strong>.
            </div>
          </div>
        </div>

        <div class="guide-step">
          <div class="step-line-wrap">
            <div class="step-circle">8</div>
          </div>
          <div class="guide-step-content">
            <div class="guide-step-title">After Approval — Proceed with WhatsApp Onboarding</div>
            <div class="guide-step-body">
              Once your Meta Business is verified:
              <ul>
                <li>Your monthly conversation limit increases to <strong>1,000+ per day</strong>.</li>
                <li>You can create your <strong>WhatsApp Business Account (WABA)</strong> and add phone numbers.</li>
                <li>You can apply for a <strong>Green Tick (Verified Badge)</strong> on your WhatsApp Business profile.</li>
                <li>Share your <strong>Meta Business Manager ID</strong> with the Tittu/WAPAPP team to complete WhatsApp integration.</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div> --}}

</div>

<!-- Decision Modal -->
<div class="modal-backdrop" id="decisionModal" role="dialog" aria-modal="true" style="display:none;">
  <div class="modal">
    <h3 id="decisionTitle">Next step</h3>
    <p id="decisionText"></p>
    <div class="submit-row">
      <a class="btn btn-primary" id="decisionLink" target="_blank" rel="noopener">Open link</a>
      <button class="btn btn-secondary" onclick="closeDecisionModal()">Close</button>
    </div>
  </div>
</div>

<!-- WhatsApp Display Name Info Modal -->
<div class="modal-backdrop" id="displayNameModal" role="dialog" aria-modal="true" style="display:none;">
  <div class="modal">
    <h3>How to choose your WhatsApp display name</h3>
    <p style="font-size:0.86rem; color:var(--text-muted); margin:8px 0 12px;">
      This is the name customers see on WhatsApp. Keep it short, clear and close to your legal / well-known brand.
    </p>
    <ul style="margin:0 0 10px 18px; padding:0; font-size:0.84rem; color:var(--text); line-height:1.6;">
      <li>✅ Use your brand or store name (e.g. <strong>TekKonnect Pro</strong>).</li>
      {{-- <li>✅ You can add a city if needed (e.g. <strong>TekKonnect Pro Mumbai</strong>).</li> --}}
      <li>❌ Don’t use slogans, ALL CAPS, emojis or extra symbols.</li>
      <li>❌ Don’t use very generic names (e.g. <em>Best Deals</em>, <em>Support Team</em>).</li>
    </ul>
    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">
      For full details, see Meta’s official rules:
      <a href="https://www.facebook.com/business/help/757569725593362" target="_blank" rel="noopener noreferrer">
        WhatsApp display name guidelines
      </a>.
    </p>
    <div class="submit-row">
      <button class="btn btn-primary" type="button" onclick="closeDisplayNameInfo()">Got it</button>
    </div>
  </div>
</div>

<!-- Business portfolio URL help modal -->
<div class="modal-backdrop" id="businessPortfolioModal" role="dialog" aria-modal="true" style="display:none;" onclick="if(event.target===this) closeBusinessPortfolioInfo()">
  <div class="modal">
    <h3>How to get this Business portfolio URL?</h3>
    <p style="font-size:0.86rem; color:var(--text-muted); margin:8px 0 12px;">
      Open <a href="https://business.facebook.com/latest/settings/business_info" target="_blank" rel="noopener noreferrer">Business info</a>, then copy the complete URL visible in your browser address bar and paste it into this field.
    </p>
    <ul style="margin:0 0 10px 18px; padding:0; font-size:0.84rem; color:var(--text); line-height:1.6;">
      <li>Click <strong>Business info</strong> directly using this link: <a href="https://business.facebook.com/latest/settings/business_info" target="_blank" rel="noopener noreferrer">https://business.facebook.com/latest/settings/business_info</a>.</li>
      <li>When the page opens, copy the full browser URL exactly as shown.</li>
      <li>Please ensure the URL includes <strong><code>business_id=...</code></strong>.</li>
      <li>Paste that complete URL here in the form.</li>
    </ul>
    <div class="help-preview-card">
      <p>Reference preview: the page should look similar to the screenshot below. Click the image to view it in a larger size.</p>
      <img
        src="https://wapapp.tittu.in/images/business-portfolio.png"
        alt="Business portfolio page preview"
        class="help-preview-thumb"
        loading="lazy"
        onclick="openBusinessPortfolioImage()"
      />
    </div>
    <div class="submit-row">
      <button class="btn btn-primary" type="button" onclick="closeBusinessPortfolioInfo()">Got it</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="businessPortfolioImageModal" role="dialog" aria-modal="true" style="display:none;" onclick="if(event.target===this) closeBusinessPortfolioImage()">
  <div class="modal image-lightbox">
    <img
      src="https://wapapp.tittu.in/images/business-portfolio.png"
      alt="Business portfolio page full preview"
    />
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── State ──────────────────────────────────────
let currentSection = 0;
const totalSections = 5;
const FREE_EMAIL_DOMAINS = ['gmail.com','yahoo.com','yahoo.in','hotmail.com','outlook.com','live.com','aol.com','icloud.com','rediffmail.com','zoho.com','yopmail.com','mailinator.com','temp-mail.org','guerrillamail.com'];

// ── Navigation ──────────────────────────────────
function goTo(idx, options = {}) {
  const skipValidation = !!options.skipValidation;
  if (!skipValidation && idx > currentSection) {
    for (let s = 0; s < idx; s++) {
      if (!validateSection(s)) {
        if (currentSection !== s) goTo(s, { skipValidation: true });
        showToast('⚠ Please fix the highlighted issues before continuing.');
        return;
      }
      markStepDone(s);
    }
  }

  document.querySelectorAll('.section-card, .summary-card').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.step-btn').forEach(b => { b.classList.remove('active'); });
  
  if (idx === 4) {
    document.getElementById('sec-4').classList.add('active');
    buildSummary();
  } else {
    document.getElementById('sec-' + idx).classList.add('active');
  }
  document.querySelectorAll('.step-btn')[idx].classList.add('active');
  currentSection = idx;

  // Submit section visibility
  document.getElementById('submitSection').classList.toggle('active', idx === 4);
  window.scrollTo({ top: 120, behavior: 'smooth' });
}

function nextSection(idx) {
  if (!validateSection(idx)) {
    showToast('⚠ Please fix the highlighted issues before continuing.');
    return;
  }
  markStepDone(idx);
  goTo(idx + 1, { skipValidation: true });
}

function prevSection(idx) {
  goTo(idx - 1);
}

function markStepDone(idx) {
  const btn = document.querySelectorAll('.step-btn')[idx];
  btn.classList.remove('active');
  btn.classList.add('done');
}

// ── Validation core ──────────────────────────────
function get(id) { return document.getElementById(id); }

function setFieldState(fieldId, state, showIds) {
  const el = get(fieldId);
  if (!el) return;
  el.classList.remove('valid','invalid','warn');
  if (state) el.classList.add(state);

  // Hide all messages first
  ['e-','b-','w-','wm-'].forEach(prefix => {
    const msgEl = get(prefix + fieldId.replace('f-', ''));
    if (msgEl) { msgEl.classList.remove('show'); msgEl.style.display = 'none'; }
  });

  // Show specified
  if (showIds) showIds.forEach(id => {
    const el = get(id);
    if (el) { el.classList.add('show'); el.style.display = 'flex'; }
  });
}

function showError(fieldId) { setFieldState(fieldId, 'invalid', ['e-' + fieldId.replace('f-','')]); }
function showBlock(fieldId, extra) { setFieldState(fieldId, 'invalid', ['b-' + fieldId.replace('f-',''), ...(extra||[])]); }
function showWarn(fieldId, warnId) { setFieldState(fieldId, 'warn', [warnId || 'w-' + fieldId.replace('f-','')]); }
function showValid(fieldId) { setFieldState(fieldId, 'valid', []); }
function showNeutral(fieldId) { setFieldState(fieldId, null, []); }

function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }
function isFreeEmail(v) {
  const domain = v.split('@')[1]?.toLowerCase();
  return FREE_EMAIL_DOMAINS.includes(domain);
}
function isValidPhone(v) { return /^\+?[0-9\s\-().]{8,20}$/.test(v); }
function isHttpsUrl(v) {
  return /^https:\/\/[^\s/$.?#].[^\s]*$/i.test(v || '');
}

let websiteReachable = null; // null=unknown, true=ok, false=failed
let websiteChecking = false;
let fbPageCheckState = null; // null=unknown, true=ok, false=failed
let fbPageCheckMsg = '';
let fbPageDebounceTimer = null;
let businessEmailCheckState = null; // null=unknown, true=ok, false=failed
let businessEmailCheckMsg = '';
let businessEmailDebounceTimer = null;

function extractBusinessIdFromPortfolioUrl(url) {
  try {
    const parsed = new URL((url || '').trim());
    if (!/^https:\/\/business\.facebook\.com\/latest\/settings\/business_info/i.test(parsed.origin + parsed.pathname)) {
      return '';
    }
    return (parsed.searchParams.get('business_id') || '').replace(/\D/g, '');
  } catch (e) {
    return '';
  }
}

function sanitizeBusinessPortfolioUrl(url) {
  const businessId = extractBusinessIdFromPortfolioUrl(url);
  return businessId
    ? 'https://business.facebook.com/latest/settings/business_info?business_id=' + businessId
    : '';
}

function setWebsiteStatus(kind, msg) {
  const el = get('website-live-status');
  if (!el) return;
  el.textContent = msg;
  if (kind === 'ok') el.style.color = '#0e5c30';
  else if (kind === 'err') el.style.color = '#7a1a10';
  else if (kind === 'pending') el.style.color = '#7a4800';
  else el.style.color = '#6b7a71';
}

function setFbPageStatus(kind, msg) {
  const el = get('fbpage-live-status');
  if (!el) return;
  el.textContent = msg;
  if (kind === 'ok') el.style.color = '#0e5c30';
  else if (kind === 'err') el.style.color = '#7a1a10';
  else if (kind === 'pending') el.style.color = '#7a4800';
  else el.style.color = '#6b7a71';
}

function setBusinessEmailStatus(kind, msg) {
  const el = get('business-email-live-status');
  if (!el) return;
  el.textContent = msg;
  if (kind === 'ok') el.style.color = '#0e5c30';
  else if (kind === 'err') el.style.color = '#7a1a10';
  else if (kind === 'pending') el.style.color = '#7a4800';
  else el.style.color = '#6b7a71';
}

async function probeWebsite(url) {
  if (!isHttpsUrl(url)) {
    websiteReachable = false;
    setWebsiteStatus('err', '🌐 Website status: invalid HTTPS URL.');
    return false;
  }
  try {
    websiteChecking = true;
    setWebsiteStatus('pending', '🌐 Checking website reachability...');
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 7000);
    await fetch(url, { method: 'GET', mode: 'no-cors', signal: controller.signal });
    clearTimeout(timer);
    websiteReachable = true;
    setWebsiteStatus('ok', '✅ Website appears reachable.');
    return true;
  } catch (e) {
    websiteReachable = false;
    setWebsiteStatus('err', '❌ Website appears unreachable. Please re-check URL.');
    return false;
  } finally {
    websiteChecking = false;
  }
}

async function validateFacebookPageRealtime(pageName) {
  if (!pageName || pageName.trim().length < 2) {
    fbPageCheckState = null;
    fbPageCheckMsg = '';
    setFbPageStatus('idle', '');
    return true;
  }

  const sanitizedUrl = sanitizeBusinessPortfolioUrl(pageName);
  if (sanitizedUrl) {
    fbPageCheckState = true;
    fbPageCheckMsg = 'Business portfolio URL looks valid and includes business_id.';
    setFbPageStatus('ok', '✅ ' + fbPageCheckMsg);
    if (get('f-fbpagename')) get('f-fbpagename').value = sanitizedUrl;
    return true;
  }
  fbPageCheckState = false;
  fbPageCheckMsg = 'Paste the full browser URL from Business info. It must contain business_id in the query string.';
  setFbPageStatus('err', '❌ ' + fbPageCheckMsg);
  return false;
}

async function validateBusinessEmailRealtime(email) {
  const v = (email || '').trim().toLowerCase();
  if (!v) {
    businessEmailCheckState = null;
    businessEmailCheckMsg = '';
    setBusinessEmailStatus('idle', '📧 Business email status: not checked yet.');
    return false;
  }

  if (!isValidEmail(v)) {
    businessEmailCheckState = false;
    businessEmailCheckMsg = 'Enter a valid email format.';
    setBusinessEmailStatus('err', '📧 ' + businessEmailCheckMsg);
    return false;
  }

  if (isFreeEmail(v)) {
    businessEmailCheckState = false;
    businessEmailCheckMsg = 'Free email domains are not allowed.';
    setBusinessEmailStatus('err', '📧 ' + businessEmailCheckMsg);
    return false;
  }

  try {
    setBusinessEmailStatus('pending', '📧 Verifying business email…');
    const resp = await fetch(@json(route('customer.readiness.validate.business-email')), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': @json(csrf_token()),
        'Accept': 'application/json',
      },
      body: JSON.stringify({ business_email: v }),
    });

    const data = await resp.json().catch(() => ({}));
    if (resp.ok && data?.valid) {
      businessEmailCheckState = true;
      businessEmailCheckMsg = data.message || 'Business email looks valid.';
      setBusinessEmailStatus('ok', '✅ ' + businessEmailCheckMsg);
      return true;
    }

    businessEmailCheckState = false;
    businessEmailCheckMsg = data?.message || 'Unable to verify business email.';
    setBusinessEmailStatus('err', '📧 ' + businessEmailCheckMsg);
    return false;
  } catch (e) {
    businessEmailCheckState = false;
    businessEmailCheckMsg = 'Validation service unavailable. Please retry.';
    setBusinessEmailStatus('err', '📧 ' + businessEmailCheckMsg);
    return false;
  }
}

function validateField(id) {
  const el = get(id);
  if (!el) return true;
  const v = el.value.trim();

  switch(id) {
    case 'f-name':
      if (!v || v.length < 3) { showError(id); return false; }
      showValid(id); return true;

    case 'f-email':
      if (!v || !isValidEmail(v)) { showError(id); return false; }
      showValid(id); return true;

    case 'f-bname':
      if (!v || v.length < 2) { showError(id); return false; }
      showValid(id); return true;

    case 'f-bemail':
      if (!v) {
        businessEmailCheckState = null;
        businessEmailCheckMsg = '';
        setBusinessEmailStatus('idle', '📧 Business email status: not checked yet.');
        setFieldState(id, 'invalid', []);
        return false;
      }
      if (!isValidEmail(v)) {
        setBusinessEmailStatus('err', '📧 Enter a valid email format.');
        setFieldState(id, 'invalid', []);
        return false;
      }
      if (isFreeEmail(v)) {
        setBusinessEmailStatus('err', '📧 Free email domains are not allowed. Use your company domain.');
        setFieldState(id, 'invalid', []);
        return false;
      }
      if (businessEmailCheckState === false) {
        setFieldState(id, 'invalid', []);
        return false;
      }
      if (businessEmailCheckState !== true) {
        const errEl = get('e-bemail');
        if (errEl) errEl.textContent = '⚠ Wait for the live check to finish.';
        showError(id);
        return false;
      }
      showValid(id);
      return true;

    case 'f-website':
      if (!v) { showError(id); return false; }
      if (v.startsWith('http://')) { showBlock(id); return false; }
      if (!v.startsWith('https://') || !v.includes('.')) { showError(id); return false; }
      if (websiteReachable === false) { showError(id); return false; }
      showValid(id); return true;

    case 'f-regdoc':
      if (!v) { showError(id); return false; }
      showValid(id); return true;

    case 'f-regdoc-file': {
      const input = el;
      if (!input.files || input.files.length === 0) {
        showError(id);
        return false;
      }
      const file = input.files[0];
      const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
      if (!isPdf) {
        showError(id);
        return false;
      }
      showValid(id);
      return true;
    }

    case 'f-metaadmin':
      if (!v) { showError(id); return false; }
      if (v === 'none') { showBlock(id); return false; }
      if (v === 'employee') { showWarn(id); return true; /* warn, not block */ }
      showValid(id); return true;

    case 'f-mfa':
      if (!v) { showError(id); return false; }
      if (v !== 'all') { showWarn(id); return true; }
      showValid(id); return true;

    case 'f-fbaccount':
      if (!v) { showError(id); return false; }
      if (v === 'no') { showBlock(id); return false; }
      showValid(id); return true;

    case 'f-fbpage':
      if (!v) { showNeutral(id); return true; }
      if (v === 'no' || v === 'unsure') { showWarn(id); return true; }
      showValid(id); return true;

    case 'f-fbpagename': {
      if (!v) { showNeutral(id); return true; }
      const sanitizedUrl = sanitizeBusinessPortfolioUrl(v);
      if (!sanitizedUrl) { showError(id); return false; }
      get('f-fbpagename').value = sanitizedUrl;
      setFbPageStatus('ok', '✅ URL format looks correct and business_id was found.');
      showValid(id); return true;
    }

    case 'f-numplan':
      if (!v) { showError(id); return false; }
      if (v === 'apponly') { showBlock(id); return false; }
      if (v === 'undecided') { showWarn(id); return true; }
      if (v === 'migrate') { showWarn(id, 'wm-numplan'); return true; }
      showValid(id); return true;

    case 'f-otp':
      if (!v) { showError(id); return false; }
      if (v === 'no') { showBlock(id); return false; }
      showValid(id); return true;

    case 'f-numtype':
      if (!v) { showError(id); return false; }
      if (v === 'voip') { showBlock(id); return false; }
      showValid(id); return true;

    case 'f-wanum':
      if (!v) { showNeutral(id); return true; /* optional */ }
      if (!isValidPhone(v)) { showError(id); return false; }
      showValid(id); return true;

    case 'f-wadisplayname':
      if (!v || v.length < 2) { showError(id); return false; }
      if (v.length > 75) { showError(id); return false; }
      showValid(id); return true;

    default:
      return true;
  }
}

// Live website reachability check on blur/change
get('f-website')?.addEventListener('blur', async () => {
  const url = get('f-website').value.trim();
  if (!url || !isHttpsUrl(url)) return;
  await probeWebsite(url);
  validateField('f-website');
});
get('f-website')?.addEventListener('change', async () => {
  const url = get('f-website').value.trim();
  if (!url || !isHttpsUrl(url)) return;
  await probeWebsite(url);
  validateField('f-website');
});

get('f-fbpage')?.addEventListener('change', async () => {
  const pageName = get('f-fbpagename')?.value?.trim() || '';
  await validateFacebookPageRealtime(pageName);
  validateField('f-fbpagename');
});

get('f-fbpagename')?.addEventListener('input', () => {
  clearTimeout(fbPageDebounceTimer);
  const pageName = get('f-fbpagename')?.value?.trim() || '';
  fbPageDebounceTimer = setTimeout(async () => {
    await validateFacebookPageRealtime(pageName);
    validateField('f-fbpagename');
  }, 550);
});

get('f-bemail')?.addEventListener('input', () => {
  clearTimeout(businessEmailDebounceTimer);
  const email = get('f-bemail')?.value?.trim() || '';
  businessEmailDebounceTimer = setTimeout(async () => {
    await validateBusinessEmailRealtime(email);
    validateField('f-bemail');
  }, 500);
});

get('f-bemail')?.addEventListener('blur', async () => {
  const email = get('f-bemail')?.value?.trim() || '';
  await validateBusinessEmailRealtime(email);
  validateField('f-bemail');
});

// ── Section validators ──────────────────────────
const sectionFields = {
  0: ['f-name','f-email','f-bname','f-bemail','f-website'],
  1: ['f-regdoc'],
  2: ['f-metaadmin','f-mfa','f-fbaccount'],
  3: ['f-numplan','f-otp','f-numtype','f-wanum','f-wadisplayname'],
};

function validateSection(idx) {
  const fields = sectionFields[idx];
  if (!fields) return true;
  let ok = true;
  fields.forEach(f => { if (!validateField(f)) ok = false; });
  updateProgress();
  return ok;
}

// ── Progress ────────────────────────────────────
function updateProgress() {
  const allFields = Object.values(sectionFields).flat();
  const filledRequired = allFields.filter(id => {
    const el = get(id);
    if (!el) return false;
    return el.value.trim().length > 0;
  }).length;
  const pct = Math.round((filledRequired / (allFields.length - 1)) * 100); // -1 for optional wanum
  const clamped = Math.min(pct, 100);
  get('topProgressBar').style.width = clamped + '%';
  get('topProgressPct').textContent = clamped + '%';
}

// ── Summary builder ──────────────────────────────
function buildSummary() {
  const checks = [
    {
      label: 'Customer name provided',
      fn: () => get('f-name')?.value.trim().length >= 3,
      note: 'Required for account identification.',
      type: 'required'
    },
    {
      label: 'Valid customer email',
      fn: () => isValidEmail(get('f-email')?.value || ''),
      note: 'Used for communication and account access.',
      type: 'required'
    },
    {
      label: 'Business name provided',
      fn: () => get('f-bname')?.value.trim().length >= 2,
      note: 'Must match your Meta Business Manager name.',
      type: 'required'
    },
    {
      label: 'Official domain business email',
      fn: () => {
        const v = get('f-bemail')?.value || '';
        return isValidEmail(v) && !isFreeEmail(v);
      },
      note: 'Free email providers are not accepted by Meta.',
      type: 'required'
    },
    {
      label: 'HTTPS-secured website',
      fn: () => {
        const v = get('f-website')?.value || '';
        return v.startsWith('https://') && v.includes('.');
      },
      note: 'Meta verifies your website during Business Verification.',
      type: 'blocker'
    },
    {
      label: 'Registration document available',
      fn: () => { const v = get('f-regdoc')?.value; return v && v !== 'none'; },
      note: 'GST or Udyam certificate required for Meta verification.',
      type: 'blocker'
    },
    {
      label: 'Meta Business Manager admin access',
      fn: () => get('f-metaadmin')?.value === 'admin',
      warnFn: () => get('f-metaadmin')?.value === 'employee',
      note: get('f-metaadmin')?.value === 'employee' ? 'Employee access — admin needs to complete setup.' : 'Admin access is required to connect WhatsApp.',
      type: 'blocker'
    },
    {
      label: 'MFA enabled for all Meta admins',
      fn: () => get('f-mfa')?.value === 'all',
      warnFn: () => ['some','none'].includes(get('f-mfa')?.value || ''),
      note: 'Partial or missing MFA — enable it in Business Settings → Security Center.',
      type: 'warning'
    },
    {
      label: 'Personal Facebook account available',
      fn: () => get('f-fbaccount')?.value === 'yes',
      note: 'Required to manage Meta Business Manager.',
      type: 'blocker'
    },
    {
      label: 'Meta Business account status shared',
      fn: () => ['yes','no','unsure'].includes(get('f-fbpage')?.value || ''),
      warnFn: () => !get('f-fbpage')?.value,
      note: 'Optional now, but sharing this helps the onboarding team assess readiness faster.',
      type: 'warning'
    },
    {
      label: 'Phone number plan confirmed',
      fn: () => ['new','migrate'].includes(get('f-numplan')?.value || ''),
      warnFn: () => get('f-numplan')?.value === 'undecided',
      note: get('f-numplan')?.value === 'undecided' ? 'You must decide on a number before completing onboarding.' : 'A number currently active on the app cannot migrate.',
      type: 'blocker'
    },
    {
      label: 'Can receive OTP (SMS or voice)',
      fn: () => get('f-otp')?.value === 'yes',
      note: 'Meta requires OTP verification to register the number.',
      type: 'blocker'
    },
    {
      label: 'Physical SIM or landline number',
      fn: () => get('f-numtype')?.value === 'physical',
      note: 'VoIP/Virtual numbers are not accepted by Meta.',
      type: 'blocker'
    },
    {
      label: 'WhatsApp Display Name provided',
      fn: () => (get('f-wadisplayname')?.value || '').trim().length >= 2,
      note: 'Must follow Meta display name rules (see link on the form).',
      type: 'required'
    },
  ];

  let passed = 0, warned = 0, failed = 0;
  const html = checks.map(c => {
    const ok = c.fn();
    const isWarn = !ok && c.warnFn && c.warnFn();

    let cls, icon;
    if (ok) { cls = 'pass'; icon = '✅'; passed++; }
    else if (isWarn) { cls = 'warn'; icon = '⚠️'; warned++; }
    else { cls = 'fail'; icon = '🚫'; failed++; }

    return `<div class="check-item ${cls}">
      <div class="check-icon">${icon}</div>
      <div>
        <div class="check-label">${c.label}</div>
        ${!ok ? `<div class="check-note">${c.note}</div>` : ''}
      </div>
    </div>`;
  }).join('');

  get('summaryChecklist').innerHTML = html;

  const total = checks.length;
  const score = Math.round((passed / total) * 100);

  // Ring
  const circumference = 251.2;
  const offset = circumference - (score / 100) * circumference;
  get('scoreCircle').setAttribute('stroke-dashoffset', offset);
  get('scorePct').textContent = score + '%';

  get('passCount').textContent = passed + ' check' + (passed !== 1 ? 's' : '') + ' passed';
  get('warnCount').textContent = warned + ' warning' + (warned !== 1 ? 's' : '');
  get('failCount').textContent = failed + ' blocker' + (failed !== 1 ? 's' : '');
}

// ── Guide toggle ──────────────────────────────────
function toggleGuide() {
  const body = get('guideBody');
  const chevron = get('guideChevron');
  const open = body.classList.toggle('open');
  chevron.classList.toggle('open', open);
}

// ── Toast ──────────────────────────────────────
function showToast(msg) {
  const t = get('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3200);
}

function openDecisionModal(isComplete) {
  const modal = get('decisionModal');
  const title = get('decisionTitle');
  const text = get('decisionText');
  const link = get('decisionLink');
  if (!modal || !title || !text || !link) return;

  if (isComplete) {
    title.textContent = 'Great! You are ready for onboarding';
    text.textContent = 'Your details look complete. Please book your onboarding slot.';
    link.href = 'https://calendly.com/tekpro-connect/25min?month=2025-09';
    link.textContent = 'Book onboarding slot';
  } else {
    title.textContent = 'Some details are incomplete';
    text.textContent = 'Please fix missing items first and follow this quick guide.';
    link.href = 'https://www.youtube.com/watch?v=y76Hp7ZG9HQ&list=PL21cVCjAc0yaiw7G8qCx-e7Pi6nczeXwS&index=1';
    link.textContent = 'Open setup guide';
  }
  modal.style.display = 'flex';
}

function closeDecisionModal() {
  const modal = get('decisionModal');
  if (modal) modal.style.display = 'none';
}

function openDisplayNameInfo() {
  const modal = get('displayNameModal');
  if (modal) modal.style.display = 'flex';
}

function closeDisplayNameInfo() {
  const modal = get('displayNameModal');
  if (modal) modal.style.display = 'none';
}

function openBusinessPortfolioInfo() {
  const modal = get('businessPortfolioModal');
  if (modal) modal.style.display = 'flex';
}

function closeBusinessPortfolioInfo() {
  const modal = get('businessPortfolioModal');
  if (modal) modal.style.display = 'none';
}

function openBusinessPortfolioImage() {
  const modal = get('businessPortfolioImageModal');
  if (modal) modal.style.display = 'flex';
}

function closeBusinessPortfolioImage() {
  const modal = get('businessPortfolioImageModal');
  if (modal) modal.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  closeDecisionModal();
  closeDisplayNameInfo();
  closeBusinessPortfolioInfo();
  closeBusinessPortfolioImage();
});

function mapToBackendPayload() {
  const docTypeMap = { gst: 'GST certificate', udyam: 'Udyam MSME certificate' };
  const mfaMap = { all: 'all_admins', some: 'some_admins', none: 'none' };
  const fbPersonalMap = { yes: 'available', no: 'not' };
  const fbPageMap = { yes: 'yes', no: 'no', unsure: 'not_sure' };
  const numPlanMap = {
    new: 'new_unused',
    migrate: 'migrate_to_api',
    apponly: 'existing_cannot_use',
    undecided: 'not_decided',
  };
  const otpMap = { yes: 'can_receive', no: 'cannot_receive' };
  const numTypeMap = { physical: 'physical', voip: 'virtual' };

  return {
    customer_name: get('f-name')?.value?.trim(),
    customer_email: get('f-email')?.value?.trim(),
    business_name: get('f-bname')?.value?.trim(),
    business_email: get('f-bemail')?.value?.trim(),
    site: get('f-website')?.value?.trim(),
    docType: docTypeMap[get('f-regdoc')?.value] || '',
    mbm_admin: get('f-metaadmin')?.value || '',
    mbm_mfa: mfaMap[get('f-mfa')?.value] || '',
    fb_personal: fbPersonalMap[get('f-fbaccount')?.value] || '',
    fb_page_created: fbPageMap[get('f-fbpage')?.value] || '',
    fb_page_name: sanitizeBusinessPortfolioUrl(get('f-fbpagename')?.value?.trim() || ''),
    num_plan: numPlanMap[get('f-numplan')?.value] || '',
    num_otp: otpMap[get('f-otp')?.value] || '',
    num_type: numTypeMap[get('f-numtype')?.value] || '',
    number: get('f-wanum')?.value?.trim(),
    whatsapp_display_name: get('f-wadisplayname')?.value?.trim(),
  };
}

// ── Submit / Save ──────────────────────────────────
async function submitForm() {
  const be = get('f-bemail')?.value?.trim() || '';
  if (be && isValidEmail(be) && !isFreeEmail(be)) {
    await validateBusinessEmailRealtime(be);
  }

  // strict checks before any action/mail/thank-you flow
  let okAll = true;
  Object.values(sectionFields).flat().forEach(id => {
    if (!validateField(id)) okAll = false;
  });

  const websiteValue = get('f-website')?.value?.trim() || '';
  if (!isHttpsUrl(websiteValue)) {
    okAll = false;
    validateField('f-website');
  }

  if (!okAll) {
    showToast('⚠ Please fix all errors before submission.');
    return;
  }

  // rebuild summary and decide next link
  buildSummary();
  const blockers = parseInt((get('failCount')?.textContent || '0').match(/\d+/)?.[0] || '0', 10);
  const warnings = parseInt((get('warnCount')?.textContent || '0').match(/\d+/)?.[0] || '0', 10);
  const isComplete = blockers === 0 && warnings === 0;
  const payload = mapToBackendPayload();

  showToast(
    isComplete
      ? '✅ Submitting your details and redirecting...'
      : '✅ Submission received. Redirecting to setup guide page...'
  );
  setTimeout(() => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = @json(route('customer.readiness.store'));
    form.enctype = 'application/x-www-form-urlencoded';
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = @json(csrf_token());
    form.appendChild(csrf);

    Object.entries(payload).forEach(([k, v]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = k;
      input.value = v || '';
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  }, 300);
}

function saveDraft() {
  const data = {};
  Object.values(sectionFields).flat().forEach(id => {
    data[id] = get(id)?.value || '';
  });
  localStorage.setItem('wapapp_draft', JSON.stringify(data));
  showToast('💾 Draft saved successfully.');
}

// ── Load draft ──────────────────────────────────
function loadDraft() {
  try {
    const saved = localStorage.getItem('wapapp_draft');
    if (!saved) return;
    const data = JSON.parse(saved);
    Object.entries(data).forEach(([id, val]) => {
      const el = get(id);
      if (el) el.value = val;
    });
    updateProgress();
  } catch(e) {}
}

// ── Init ──────────────────────────────────────
loadDraft();
updateProgress();

const oldInput = @json(session()->getOldInput());
const oldToField = {
  customer_name: 'f-name',
  customer_email: 'f-email',
  business_name: 'f-bname',
  business_email: 'f-bemail',
  site: 'f-website',
  docType: 'f-regdoc',
  mbm_admin: 'f-metaadmin',
  mbm_mfa: 'f-mfa',
  fb_personal: 'f-fbaccount',
  fb_page_created: 'f-fbpage',
  fb_page_name: 'f-fbpagename',
  num_plan: 'f-numplan',
  num_otp: 'f-otp',
  num_type: 'f-numtype',
  number: 'f-wanum',
  whatsapp_display_name: 'f-wadisplayname',
};
Object.entries(oldToField).forEach(([backendKey, fieldId]) => {
  const val = oldInput?.[backendKey];
  if (val === undefined || val === null) return;
  const el = get(fieldId);
  if (el) el.value = val;
});

const serverErrors = @json($errors->getMessages());
const backendToField = {
  customer_name: 'f-name',
  customer_email: 'f-email',
  business_name: 'f-bname',
  business_email: 'f-bemail',
  site: 'f-website',
  docType: 'f-regdoc',
  mbm_admin: 'f-metaadmin',
  mbm_mfa: 'f-mfa',
  fb_personal: 'f-fbaccount',
  fb_page_created: 'f-fbpage',
  fb_page_name: 'f-fbpagename',
  num_plan: 'f-numplan',
  num_otp: 'f-otp',
  num_type: 'f-numtype',
  number: 'f-wanum',
  whatsapp_display_name: 'f-wadisplayname',
};
Object.entries(serverErrors || {}).forEach(([backendKey, msgs]) => {
  const fieldId = backendToField[backendKey];
  if (!fieldId) return;
  const inputEl = get(fieldId);
  if (inputEl) inputEl.classList.add('invalid');
  const uiKey = fieldId.replace('f-', '');
  const errEl = get('e-' + uiKey);
  if (errEl && msgs?.[0]) {
    errEl.textContent = '⚠ ' + msgs[0];
    errEl.classList.add('show');
    errEl.style.display = 'flex';
  }
});
updateProgress();

// Update progress on any input
document.addEventListener('input', updateProgress);
document.addEventListener('change', updateProgress);
</script>
</body>
</html>
