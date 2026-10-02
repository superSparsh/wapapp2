{{-- Compact brand mark for app/admin sidebars --}}
@php
    $lightSrc = asset('images/tittu-logo.jpeg');
    $darkSrc = asset('images/logo.png');
@endphp

<span {{ $attributes->class('relative inline-flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg') }} style="width: 50px; height: 70px">
    <img
        src="{{ $lightSrc }}"
        alt="WapApp"
        class="h-full w-full object-cover dark:hidden"
        width="44"
        height="44"
    >
    <img
        src="{{ $darkSrc }}"
        alt="WapApp"
        class="hidden h-full w-full object-cover dark:block"
        width="44"
        height="44"
    >
</span>
