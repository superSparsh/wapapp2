{{-- Compact brand mark for 2FA challenge / setup cards --}}
@php
    $lightSrc = asset('images/tittu-logo.jpeg');
    $darkSrc = asset('images/auth/logo-green.png');
@endphp

<span {{ $attributes->class('relative inline-flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl') }}>
    <img
        src="{{ $lightSrc }}"
        alt="WapApp"
        class="h-full w-full object-cover dark:hidden"
        width="96"
        height="96"
    >
    <img
        src="{{ $darkSrc }}"
        alt="WapApp"
        class="hidden h-full w-full object-cover dark:block"
        width="96"
        height="96"
    >
</span>
