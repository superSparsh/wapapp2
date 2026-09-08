@props([
    'open' => false,
    'closeHref' => null,
    'campaign' => null,
])

@php
    $closeUrl = $closeHref ?? ($campaign ? route('automation.drip.audience', $campaign) : '#');
@endphp

<div
  id="modal-trigger-confirm"
  data-modal="trigger-confirm"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-trigger-confirm"
>
  <div class="flex w-full max-w-[603px] flex-col gap-5 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <h2
      id="modal-title-trigger-confirm"
      class="w-full text-center text-2xl font-bold leading-[1.5] text-text-primary"
    >
      Are you sure
    </h2>

    <div class="flex w-full flex-col items-start overflow-hidden rounded-xl border border-solid border-border-light bg-muted-surface p-4">
      <p class="w-full text-center text-base font-medium leading-[1.5] text-text-primary">
        You are trying to manually trigger the automation for this contact, although trigger criteria may not meet.
      </p>
    </div>

    <div class="flex w-full items-center justify-end">
      <div class="flex min-w-0 flex-1 items-center gap-3">
        @if (! empty($closeHref))
          <a
            href="{{ $closeUrl }}"
            class="fd-btn flex min-w-0 flex-1 items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-center text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </a>
        @else
          <button
            type="button"
            data-trigger-cancel
            class="fd-btn flex min-w-0 flex-1 items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-center text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </button>
        @endif
        <button
          type="button"
          data-trigger-confirm
          class="fd-btn flex min-w-0 flex-1 items-center justify-center rounded bg-green-500 px-4 py-3 text-center text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          Confirm
        </button>
      </div>
    </div>
  </div>
</div>
