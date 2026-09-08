@props([
  'label',
  'value',
  'icon' => 'chart',
])

@php
  $iconSrc = match ($icon) {
    'send' => asset('images/icons/send.svg'),
    'megaphone' => asset('images/icons/device-message.svg'),
    'wrench' => asset('images/icons/task-square.svg'),
    'headset' => asset('images/icons/microphone.svg'),
    'chart' => asset('images/icons/data.svg'),
    default => asset('images/icons/data.svg'),
  };
@endphp

<div {{ $attributes->class(['flex items-center gap-4 rounded-lg border border-[0.5px] border-border-light bg-elevated p-4']) }}>
  <div class="min-w-0 flex-1">
    <p class="text-sm leading-[1.4] text-text-primary opacity-70" style="font-family: var(--font-display)">{{ $label }}</p>
    <p data-credit-value class="mt-2 text-2xl font-bold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">{{ $value }}</p>
  </div>
  <div class="flex size-[62px] shrink-0 items-center justify-center rounded-full bg-green-50">
    <img src="{{ $iconSrc }}" alt="" class="size-7 opacity-80" width="28" height="28">
  </div>
</div>
