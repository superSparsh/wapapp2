@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'primary' => 'bg-auth-gradient text-white font-extrabold',
        'otp' => 'bg-green-600 text-white text-[10px] font-semibold leading-[1.2] px-2 py-1 rounded-xl',
        default => 'bg-auth-gradient text-white font-extrabold',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "flex w-full items-center justify-center rounded-[12px] p-[14px] text-base leading-[1.5] transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 {$classes}"]) }}
>
    {{ $slot }}
</button>
