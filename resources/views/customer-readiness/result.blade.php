{{-- Placeholder result shell — replace with imported legacy page. --}}
@php
    $result = is_array($result ?? null) ? $result : [];
    $eligible = (bool) ($result['eligible'] ?? false);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $result['title'] ?? 'Customer readiness' }}</title>
</head>
<body>
    <main data-readiness-result="{{ $eligible ? 'eligible' : 'not_eligible' }}">
        <h1>{{ $result['title'] ?? 'Thank you' }}</h1>
        <p>{{ $result['message'] ?? '' }}</p>
        @if (! empty($result['link']))
            <p><a href="{{ $result['link'] }}">{{ $result['link_text'] ?? 'Continue' }}</a></p>
        @endif
    </main>
</body>
</html>
