@props([
    'backUrl' => null,
    'nextLabel' => 'Next',
    'showNext' => true,
])

<div class="flex w-full items-center justify-between gap-3 pt-1">
  @if (filled($backUrl))
    <a
      href="{{ $backUrl }}"
      class="fd-btn-sm inline-flex items-center justify-center rounded border border-solid border-border bg-elevated px-4 py-2 text-sm font-medium text-text-body transition-colors hover:border-green-500 hover:text-green-600"
    >
      Back
    </a>
  @else
    <span></span>
  @endif

  @if ($showNext)
    <button
      type="submit"
      class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-2 text-primary-2 transition-opacity hover:opacity-90"
    >
      {{ $nextLabel }}
    </button>
  @elseif (isset($slot) && trim((string) $slot) !== '')
    <div class="flex items-center gap-3">
      {{ $slot }}
    </div>
  @endif
</div>
