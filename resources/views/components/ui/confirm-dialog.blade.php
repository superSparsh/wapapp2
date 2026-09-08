<div
  id="app-confirm-dialog"
  class="fixed inset-0 z-[100] hidden items-center justify-center bg-[rgba(0,0,0,0.6)] p-4"
  role="dialog"
  aria-modal="true"
  aria-labelledby="app-confirm-title"
  aria-hidden="true"
>
  <button type="button" data-confirm-backdrop class="absolute inset-0" aria-label="Close dialog"></button>

  <div class="relative z-10 flex w-full max-w-[480px] flex-col gap-5 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex flex-col gap-2 text-center">
      <h2 id="app-confirm-title" data-confirm-title class="text-xl font-bold leading-[1.5] text-text-primary">
        Are you sure?
      </h2>
      <p data-confirm-message class="text-sm font-medium leading-[1.5] text-text-body"></p>
    </div>

    <div class="flex w-full items-center gap-3" data-confirm-actions>
      <button
        type="button"
        data-confirm-cancel
        class="fd-btn flex min-w-0 flex-1 items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50"
      >
        Cancel
      </button>
      <button
        type="button"
        data-confirm-submit
        class="fd-btn flex min-w-0 flex-1 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-opacity hover:opacity-90"
      >
        Confirm
      </button>
    </div>

    <div class="hidden w-full justify-center" data-alert-actions>
      <button
        type="button"
        data-alert-ok
        class="fd-btn inline-flex min-w-[140px] items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-opacity hover:opacity-90"
      >
        OK
      </button>
    </div>
  </div>
</div>
