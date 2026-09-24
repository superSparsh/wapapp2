{{-- Sticky side tab — owner Open Inbox only (direct line-login uses banner Sign out) --}}
@if (! empty($isLineContextLocked) && empty($isLineDirectLogin))
  <form method="POST" action="{{ route('profile.phone-lines.exit-context') }}" class="line-context-exit-form">
    @csrf
    <button type="submit" class="line-context-exit-tab" title="Exit number mode and use all numbers again">
      Exit number mode
    </button>
  </form>

  <style>
    .line-context-exit-form { margin: 0; padding: 0; }
    .line-context-exit-tab {
      position: fixed;
      top: 42%;
      right: 0;
      z-index: 1040;
      transform: rotate(-90deg);
      transform-origin: bottom right;
      margin: 0;
      padding: 6px 14px 8px;
      border: none;
      border-radius: 7px 7px 0 0;
      cursor: pointer;
      white-space: nowrap;
      font-size: 12px;
      font-weight: 600;
      line-height: 1.35;
      letter-spacing: 0.02em;
      color: #fff;
      text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
      background: #059669;
      box-shadow: -2px 3px 12px rgba(5, 150, 105, 0.35);
      transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }
    .line-context-exit-tab:hover {
      background: #047857;
      box-shadow: -3px 4px 16px rgba(5, 150, 105, 0.45);
      transform: rotate(-90deg) translateX(-3px);
    }
    .line-context-exit-tab:focus { outline: none; }
    .line-context-exit-tab:focus-visible {
      outline: 2px solid rgba(167, 243, 208, 0.95);
      outline-offset: 3px;
    }
    @media (max-width: 576px) {
      .line-context-exit-tab {
        top: auto !important;
        bottom: 84px;
        font-size: 11px;
        padding: 5px 12px 7px;
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .line-context-exit-tab { transition: none; }
      .line-context-exit-tab:hover { transform: rotate(-90deg); }
    }
  </style>
@endif
