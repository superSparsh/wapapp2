@props([
    'cancelRoute' => 'campaigns.index',
    'showCancel' => true,
    'previousRoute' => null,
    'nextRoute' => null,
    'nextLabel' => 'Next step',
    'previousLabel' => 'Previous Step',
    'secondaryNextRoute' => null,
    'secondaryNextLabel' => null,
    'secondaryOpenModal' => null,
])

@php
$resolveUrl = fn ($val) => $val && str_starts_with($val, 'http') ? $val : ($val ? route($val) : null);
$cancelUrl = $resolveUrl($cancelRoute);
$prevUrl = $resolveUrl($previousRoute);
@endphp

<div class="sticky bottom-0 z-50 border-t border-[rgba(90,90,90,0.15)] bg-elevated p-4">
  <div class="flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      @if ($showCancel)
        <a href="{{ $cancelUrl }}" class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-elevated px-6 py-3 text-base font-semibold text-green-500 transition-colors hover:bg-surface">
          Cancel
        </a>
      @elseif ($prevUrl)
        <a href="{{ $prevUrl }}" class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-elevated px-6 py-3 text-base font-semibold text-green-500 transition-colors hover:bg-surface">
          {{ $previousLabel }}
        </a>
      @endif
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      @if ($showCancel && $prevUrl)
        <a href="{{ $prevUrl }}" class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-elevated px-6 py-3 text-base font-semibold text-green-500 transition-colors hover:bg-surface">
          {{ $previousLabel }}
        </a>
      @endif

      @if ($secondaryNextLabel && $secondaryOpenModal)
        <button
          type="button"
          data-open-modal="{{ $secondaryOpenModal }}"
          class="fd-btn inline-flex items-center justify-center rounded-lg bg-green-500 px-6 py-3 text-base font-semibold text-primary-2 transition-colors hover:opacity-90"
        >
          {{ $secondaryNextLabel }}
        </button>
      @elseif ($secondaryNextRoute && $secondaryNextLabel)
        <a href="{{ $secondaryNextRoute }}" class="fd-btn inline-flex items-center justify-center rounded-lg bg-green-500 px-6 py-3 text-base font-semibold text-primary-2 transition-colors hover:opacity-90">
          {{ $secondaryNextLabel }}
        </a>
      @endif

      @if ($nextRoute)
        <button type="submit" form="campaign-wizard-form" class="fd-btn inline-flex items-center justify-center rounded-lg bg-green-500 px-6 py-3 text-base font-semibold text-primary-2 transition-colors hover:opacity-90">
          {{ $nextLabel }}
        </button>
      @endif
    </div>
  </div>
</div>
