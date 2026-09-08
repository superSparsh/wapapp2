@props(['class' => 'size-6'])

<img src="{{ asset('images/icons/header/search-normal.svg') }}" alt="" {{ $attributes->merge(['class' => $class]) }} width="24" height="24">
