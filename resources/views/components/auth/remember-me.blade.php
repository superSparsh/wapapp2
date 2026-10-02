@props([
    'checked' => null,
])

@php
    $isChecked = $checked ?? (bool) old('remember');
@endphp

<label {{ $attributes->class('group flex cursor-pointer items-center gap-1.5 select-none') }}>
    <input
        type="checkbox"
        name="remember"
        value="1"
        class="sr-only"
        @checked($isChecked)
    >
    <span
        class="relative flex size-5 shrink-0 items-center justify-center rounded border border-border bg-elevated transition-colors group-has-[:checked]:border-green-500 group-has-[:checked]:bg-green-500 group-focus-within:ring-2 group-focus-within:ring-green-500/40"
        aria-hidden="true"
    >
        <svg class="size-3.5 text-white opacity-0 transition-opacity group-has-[:checked]:opacity-100" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 10.5 8.2 13.5 15 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
    <span class="text-sm font-medium leading-[1.4] text-text-muted">Remember me</span>
</label>
