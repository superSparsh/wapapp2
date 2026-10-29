<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicons/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicons/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicons/apple-touch-icon.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/favicons/icon-192.png') }}">
<link rel="apple-touch-icon" sizes="512x512" href="{{ asset('images/favicons/icon-512.png') }}">
{{-- Relative URLs so manifest/SW stay same-origin (asset() can break installability on mixed hosts). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#16a34a" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1f2a" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'WapApp') }}">
<meta name="application-name" content="{{ config('app.name', 'WapApp') }}">
<meta name="format-detection" content="telephone=no">
