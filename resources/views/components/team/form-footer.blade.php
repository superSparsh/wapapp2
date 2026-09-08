@props([
    'cancelUrl',
    'submitLabel' => 'Save changes',
    'hint' => null,
    'showArrow' => false,
])

<div class="flex flex-col-reverse gap-3 bg-surface/50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
  @if ($hint)
    <p class="text-xs text-text-subtle opacity-70">{{ $hint }}</p>
  @else
    <span></span>
  @endif

  <div class="flex items-center justify-end gap-2.5">
    <a
      href="{{ $cancelUrl }}"
      class="fd-btn-sm inline-flex items-center justify-center rounded border border-green-500 bg-elevated px-6 py-3 text-xs font-semibold text-green-500 transition-opacity hover:opacity-90"
    >
      Cancel
    </a>
    <button
      type="submit"
      class="fd-btn-sm inline-flex items-center justify-center gap-2 rounded bg-green-500 px-6 py-3 text-xs font-semibold text-primary-2 transition-opacity hover:opacity-90"
    >
      {{ $submitLabel }}
      @if ($showArrow)
        <img src="{{ asset('images/icons/sidebar/menu/chevron-right.svg') }}" alt="" class="size-3.5 brightness-0 invert" width="14" height="14">
      @endif
    </button>
  </div>
</div>
