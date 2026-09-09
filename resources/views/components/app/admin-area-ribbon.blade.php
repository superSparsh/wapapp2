{{-- Legacy sticky right-side "Admin Area" ribbon --}}
@php
  $showAdminArea = ! empty($isAdminImpersonating) || ! empty($canAccessAdminView);
@endphp

@if ($showAdminArea)
  @if (! empty($isAdminImpersonating))
    <form method="post" action="{{ route('admin.impersonation.stop') }}" class="admin-area-ribbon-form">
      @csrf
      <button type="submit" class="admin-area-ribbon" title="Return to platform admin">
        Admin Area
      </button>
    </form>
  @else
    <a href="{{ route('admin.enter-from-app') }}" class="admin-area-ribbon" title="Open platform admin">
      Admin Area
    </a>
  @endif

  <style>
    .admin-area-ribbon-form {
      margin: 0;
      padding: 0;
    }

    .admin-area-ribbon {
      position: fixed;
      top: 35%;
      right: 0;
      z-index: 1040;
      display: inline-block;
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
      text-decoration: none;
      text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
      background: #16a34a;
      box-shadow: -2px 3px 12px rgba(22, 163, 74, 0.35);
      transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .admin-area-ribbon:hover {
      background: #15803d;
      box-shadow: -3px 4px 16px rgba(22, 163, 74, 0.45);
      transform: rotate(-90deg) translateX(-3px);
      color: #fff;
    }

    .admin-area-ribbon:focus {
      outline: none;
    }

    .admin-area-ribbon:focus-visible {
      outline: 2px solid rgba(187, 247, 208, 0.95);
      outline-offset: 3px;
    }

    @media (max-width: 576px) {
      .admin-area-ribbon {
        top: auto !important;
        bottom: 84px;
        font-size: 11px;
        padding: 5px 12px 7px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .admin-area-ribbon {
        transition: none;
      }
      .admin-area-ribbon:hover {
        transform: rotate(-90deg);
      }
    }
  </style>
@endif
