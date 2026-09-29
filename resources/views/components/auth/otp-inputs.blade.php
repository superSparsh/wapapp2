@props([
    'name' => 'code',
    'label' => 'Authentication code',
    'digits' => 6,
    'error' => null,
    'old' => '',
])

@php
    $oldCode = preg_replace('/\D+/', '', (string) ($old ?: old($name, '')));
@endphp

<div class="flex flex-col gap-3" {{ $attributes->only('class') }}>
    <x-form.label for="otp-1">{{ $label }}</x-form.label>
    <div class="flex gap-3" data-otp-inputs>
        @for ($i = 1; $i <= $digits; $i++)
            <input
                id="otp-{{ $i }}"
                type="text"
                inputmode="numeric"
                autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}"
                maxlength="1"
                placeholder="-"
                value="{{ substr($oldCode, $i - 1, 1) }}"
                aria-label="Digit {{ $i }}"
                class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-center text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                data-otp-digit
            >
        @endfor
    </div>
    <input type="hidden" name="{{ $name }}" id="{{ $name }}" value="{{ $oldCode }}" data-otp-hidden>
    @if ($error)
        <p class="text-sm font-medium text-red-500">{{ $error }}</p>
    @endif
</div>
