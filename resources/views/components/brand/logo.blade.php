{{--
  Site brand mark:
  - Light mode: tittu-logo.jpeg
  - Dark mode: existing mark (app sidebar logo.png / auth logo-green.png)
--}}
@props([
    'variant' => 'app',
])

@php
    $lightSrc = asset('images/tittu-logo.jpeg');
    $darkSrc = $variant === 'auth'
        ? asset('images/auth/logo-green.png')
        : asset('images/logo.png');
@endphp

<span {{ $attributes->class('shrink-0 overflow-hidden rounded-2xl') }} style="width: 250px; height: 250px;left: -13%;">
    <img
        src="{{ $lightSrc }}"
        alt="WapApp"
        class="h-full w-full object-cover dark:hidden"
        width="80"
        height="80"
    >
    <img
        src="{{ $darkSrc }}"
        alt="WapApp"
        class="hidden h-full w-full object-cover dark:block"
        width="80"
        height="80"
    >
</span>
