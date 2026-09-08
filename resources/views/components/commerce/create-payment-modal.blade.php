@props(['open' => false, 'closeHref' => null, 'action' => '#'])

<div
  id="modal-create-payment"
  data-modal="create-payment"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-create-payment"
>
  <div class="flex w-full max-w-[597px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-create-payment" class="text-2xl font-bold leading-[1.5] text-text-primary">Create New Payment</h2>
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

    <form method="POST" action="{{ $action }}" id="form-create-payment">
      @csrf
      <div class="w-full rounded-xl border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-8">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label for="customer_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Customer Name<span class="text-[red]">*</span>
              </label>
              <input
                id="customer_name"
                name="customer_name"
                type="text"
                placeholder="Enter Customer Name"
                value="{{ old('customer_name') }}"
                required
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
              @error('customer_name')
                <p class="text-xs text-red-500">{{ $message }}</p>
              @enderror
            </div>
            <div class="flex flex-col gap-2">
              <label for="customer_phone" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Customer Number<span class="text-[red]">*</span>
              </label>
              <input
                id="customer_phone"
                name="customer_phone"
                type="text"
                placeholder="Enter Number (e.g. 9876543210)"
                value="{{ old('customer_phone') }}"
                required
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
              @error('customer_phone')
                <p class="text-xs text-red-500">{{ $message }}</p>
              @enderror
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label for="currency" class="text-sm font-semibold leading-[1.4] text-text-primary">Currency</label>
              <select
                id="currency"
                name="currency"
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:outline-none"
              >
                <option value="INR">INR</option>
                <option value="USD">USD</option>
              </select>
            </div>
            <div class="flex flex-col gap-2">
              <label for="amount" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Amount<span class="text-[red]">*</span>
              </label>
              <input
                id="amount"
                name="amount"
                type="number"
                min="1"
                step="0.01"
                placeholder="Enter Amount"
                value="{{ old('amount') }}"
                required
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
              @error('amount')
                <p class="text-xs text-red-500">{{ $message }}</p>
              @enderror
            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 flex w-full items-center justify-end">
        <button
          type="submit"
          class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          Create &amp; Send via WhatsApp
        </button>
      </div>
    </form>
  </div>
</div>
