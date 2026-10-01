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
  $hasInfo = isset($info) && trim((string) $info) !== '';
@endphp

<div {{ $attributes->class(['relative flex items-center gap-4 rounded-lg border border-[0.5px] border-border-light bg-elevated p-4']) }}>
  <div class="min-w-0 flex-1">
    <div class="flex items-center gap-1.5">
      <p class="text-sm leading-[1.4] text-text-primary opacity-70" style="font-family: var(--font-display)">{{ $label }}</p>
      @if ($hasInfo)
        <button
          type="button"
          data-credit-info-trigger
          class="inline-flex size-5 shrink-0 items-center justify-center rounded-full text-text-muted transition-colors hover:bg-green-50 hover:text-green-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
          aria-label="Free service messages info"
          aria-expanded="false"
        >
          <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
        </button>
      @endif
    </div>
    <p data-credit-value class="mt-2 text-2xl font-bold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">{{ $value }}</p>
  </div>
  <div class="flex size-[62px] shrink-0 items-center justify-center rounded-full bg-green-50">
    <img src="{{ $iconSrc }}" alt="" class="size-7 opacity-80" width="28" height="28">
  </div>

  @if ($hasInfo)
    <div
      data-credit-info-panel
      class="pointer-events-none absolute left-0 right-0 top-[calc(100%+8px)] z-30 hidden rounded-xl border border-border bg-elevated p-3 text-left shadow-lg"
      role="tooltip"
    >
      <div data-credit-info-body class="space-y-2 text-xs leading-relaxed text-text-body">
        {{ $info }}
      </div>
    </div>
  @endif
</div>
