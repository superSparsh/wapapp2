@props(['for' => null])

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->merge(['class' => 'text-sm font-semibold leading-[1.4] text-text-primary']) }}
>
    {{ $slot }}
</label>
