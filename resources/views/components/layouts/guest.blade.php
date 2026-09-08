<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'WapApp') }}</title>

    <x-layouts.theme-boot />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('scripts')
</head>
<body class="min-h-screen bg-surface font-sans text-text-primary antialiased">
    {{ $slot }}
    <x-ui.confirm-dialog />
    @php
      $flashToasts = [];

      if (session('status')) {
          $flashToasts[] = ['type' => 'success', 'message' => session('status')];
      }

      if (session('error')) {
          $flashToasts[] = ['type' => 'error', 'message' => session('error')];
      }
    @endphp
    @if (! empty($flashToasts))
      <script>window.__FLASH_TOASTS__ = @json($flashToasts);</script>
    @endif
</body>
</html>
