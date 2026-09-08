<div
  id="modal-order-details"
  data-modal="order-details"
  data-order-detail-base="{{ url('/commerce/orders') }}"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open ?? false,
    'hidden' => ! ($open ?? false),
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-order-details"
>
  <div class="flex w-full max-w-[479px] flex-col gap-3 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-order-details" class="text-2xl font-bold leading-[1.5] text-text-primary">Order Details</h2>
        <p class="text-sm leading-[1.4] text-text-subtle opacity-50" data-order-subtitle>Loading order…</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="shrink-0">
        <img src="{{ asset('images/commerce/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <div class="hidden rounded-lg bg-red-50 p-3 text-sm text-red-600" data-order-error></div>

    <div class="w-full rounded-xl border border-border-light bg-muted-surface p-4">
      <div class="flex items-center gap-3">
        <img src="{{ asset('images/commerce/avatar-placeholder.svg') }}" alt="" class="size-12 shrink-0" width="48" height="48">
        <div class="min-w-0 flex-1">
          <p class="text-base font-semibold leading-[1.4] text-text-primary" data-order-customer-name>—</p>
          <div class="mt-1 flex items-center gap-1">
            <img src="{{ asset('images/commerce/whatsapp.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            <span class="text-sm font-medium leading-[1.4] text-text-subtle" data-order-customer-phone>—</span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex w-full flex-col gap-4 rounded-xl border border-border-light bg-muted-surface p-4">
      <div class="flex items-center gap-2">
        <img src="{{ asset('images/commerce/calendar.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
        <span class="text-sm font-medium leading-[1.5] text-[#848484]" data-order-date>—</span>
      </div>

      <div class="flex items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 flex-col gap-4">
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-base font-medium leading-[1.5] text-text-body">Order Status</span>
            <select
              data-order-status-select
              class="rounded-lg border border-border bg-elevated px-2 py-1.5 text-sm text-text-body focus:outline-none"
            ></select>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-base font-medium leading-[1.5] text-text-body">Payment Status</span>
            <span
              class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-xs font-medium leading-[1.2] text-green-600"
              data-order-payment-status
            >—</span>
          </div>
        </div>
        <div class="shrink-0 text-green-700">
          <p class="text-base font-medium leading-[1.5]">Total Amount</p>
          <p class="text-base font-semibold leading-normal" data-order-total>—</p>
        </div>
      </div>

      <a
        href="#"
        target="_blank"
        rel="noopener"
        class="hidden text-sm font-medium text-green-600 underline"
        data-order-payment-link
      >Open payment link</a>
    </div>

    <h3 class="text-base font-semibold leading-[1.4] text-text-primary">Ordered Items</h3>
    <div class="flex max-h-56 flex-col gap-3 overflow-y-auto" data-order-items>
      <p class="text-sm text-text-subtle">Loading items…</p>
    </div>
  </div>
</div>
