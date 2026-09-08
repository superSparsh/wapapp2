<div
  id="modal-order-details"
  data-modal="order-details"
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
        <p class="text-sm leading-[1.4] text-text-subtle opacity-50">complete Order Details</p>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="shrink-0">
          <img src="{{ asset('images/commerce/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @else
        <button type="button" data-modal-close aria-label="Close" class="shrink-0">
          <img src="{{ asset('images/commerce/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      @endif
    </div>

    <div class="w-full rounded-xl border border-border-light bg-muted-surface p-4">
      <div class="flex items-center gap-3">
        <img src="{{ asset('images/commerce/avatar-placeholder.svg') }}" alt="" class="size-12 shrink-0" width="48" height="48">
        <div class="min-w-0 flex-1">
          <p class="text-base font-semibold leading-[1.4] text-text-primary">Customer Name goes here</p>
          <div class="mt-1 flex items-center gap-1">
            <img src="{{ asset('images/commerce/whatsapp.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
            <span class="text-sm font-medium leading-[1.4] text-text-subtle">+91 25896 69852</span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex w-full flex-col gap-4 rounded-xl border border-border-light bg-muted-surface p-4">
      <div class="flex items-center gap-2">
        <img src="{{ asset('images/commerce/calendar.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
        <span class="text-sm font-medium leading-[1.5] text-[#848484]">17 Nov 2025 • 09:54 AM</span>
      </div>

      <div class="flex items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 flex-col gap-4">
          <div class="flex items-center gap-4">
            <span class="text-base font-medium leading-[1.5] text-text-body">Order Status</span>
            <span class="inline-flex items-center justify-center rounded bg-[rgba(59,130,246,0.1)] px-2 py-1 text-xs font-medium leading-[1.2] text-stat-blue">New</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-base font-medium leading-[1.5] text-text-body">Payment Status</span>
            <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-xs font-medium leading-[1.2] text-green-600">Paid</span>
          </div>
        </div>
        <div class="shrink-0 text-green-700">
          <p class="text-base font-medium leading-[1.5]">Total Amount</p>
          <p class="text-base font-semibold leading-normal">₹ 33,900</p>
        </div>
      </div>
    </div>

    <h3 class="text-base font-semibold leading-[1.4] text-text-primary">Ordered Items</h3>

    <div class="flex w-full items-center gap-3">
      <div class="size-16 shrink-0 rounded bg-border"></div>
      <div class="min-w-0 flex-1">
        <p class="text-base font-semibold leading-[1.4] text-text-primary">WhatsApp Basic</p>
        <p class="text-sm font-normal leading-[1.4] text-text-subtle">Quantity: 1</p>
      </div>
      <p class="shrink-0 text-base font-semibold leading-normal text-green-700">₹ 33,900</p>
    </div>
  </div>
</div>
