@props(['open' => false, 'closeHref' => null, 'action' => '#', 'config' => null, 'templates' => collect()])

<div
  id="modal-payment-configuration"
  data-modal="payment-configuration"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-payment-configuration"
>
  <div class="flex max-h-[90vh] w-full max-w-[882px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-payment-configuration" class="text-2xl font-bold leading-[1.5] text-text-primary">Setup Payment Configuration</h2>
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

    <form method="POST" action="{{ $action }}" id="form-payment-config">
      @csrf
      <div class="flex flex-col items-start lg:flex-row">
        <div class="flex w-full flex-col gap-4 lg:w-[401px] lg:shrink-0">
          <x-form.input
            id="config_client_name"
            name="client_name"
            placeholder="Enter Client Name"
            :value="old('client_name', $config?->client_name)"
            class="placeholder:text-text-muted"
          >
            <x-slot:label>Client Name</x-slot:label>
          </x-form.input>

          <x-form.input
            id="config_razorpay_key"
            name="razorpay_key"
            :value="old('razorpay_key', $config?->razorpay_key)"
            placeholder="rzp_live_xxxxxxxxxxxxxxxx"
            class="text-text-body"
          >
            <x-slot:label>Razorpay Key</x-slot:label>
          </x-form.input>

          <x-form.input
            id="config_razorpay_secret"
            name="razorpay_secret"
            type="password"
            placeholder="{{ $config ? '••••••• (leave blank to keep current)' : 'Enter Razorpay Secret' }}"
            class="pr-12 placeholder:text-text-muted"
          >
            <x-slot:label>Razorpay Secret</x-slot:label>
            <x-slot:suffix>
              <img src="{{ asset('images/commerce/eye.svg') }}" alt="" class="size-5" width="20" height="20">
            </x-slot:suffix>
          </x-form.input>

          <div class="flex w-full flex-col gap-3">
            <label for="payment_template_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Select Payment Template
            </label>
            <select
              id="payment_template_id"
              name="payment_template_id"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:outline-none"
            >
              <option value="">— Select Template —</option>
              @foreach ($templates as $tpl)
                <option
                  value="{{ $tpl->id }}"
                  @selected(old('payment_template_id', $config?->payment_template_id) == $tpl->id)
                >{{ $tpl->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="flex w-full flex-col gap-3">
            <label for="confirmation_template_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Select Payment Confirmation Template <span class="font-normal text-text-subtle">(Optional)</span>
            </label>
            <select
              id="confirmation_template_id"
              name="confirmation_template_id"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:outline-none"
            >
              <option value="">— None —</option>
              @foreach ($templates as $tpl)
                <option
                  value="{{ $tpl->id }}"
                  @selected(old('confirmation_template_id', $config?->confirmation_template_id) == $tpl->id)
                >{{ $tpl->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="relative w-full shrink-0 px-2 py-4 lg:w-[441px]">
          <div class="relative mx-auto h-[518px] w-full max-w-[425px] overflow-hidden">
            <div class="pointer-events-none absolute inset-[0_2.5px_-338px_3.5px] rounded-[62px] border border-white/60 shadow-[inset_0px_0px_8px_0px_rgba(0,0,0,0.3)]">
              <div class="absolute inset-0 rounded-[62px] bg-muted-surface"></div>
            </div>
            <div class="absolute inset-[4px_6.5px_-334px_7.5px] rounded-[58px] bg-black"></div>
            <div class="absolute left-0.5 top-[136px] h-[30px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute left-0.5 top-[198px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute left-0.5 top-[278px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute right-[-0.5px] top-[220px] h-[100px] w-[3px] rounded-br-[1px] rounded-tr-[1px] bg-border shadow-[inset_-1px_0px_2px_0px_white]"></div>

            <div class="absolute inset-[22px_24px_0_26px] overflow-hidden rounded-t-[40px] bg-elevated">
              <img
                src="{{ asset('images/commerce/payment-preview.png') }}"
                alt=""
                class="absolute left-1/2 top-0 h-[496px] w-[375px] max-w-none -translate-x-1/2 rounded-t-lg object-cover object-top"
                width="375"
                height="496"
              >
            </div>

            <div class="absolute left-[43px] top-[315px] flex w-[354px] items-start gap-3 rounded-lg border border-border bg-elevated p-3.5">
              <div class="min-w-0 flex-1 text-xs font-normal leading-[1.4] text-text-muted">
                <p>Payment Link for Order</p>
                <p>Click the link to complete your payment of ₹<span id="preview-amount">—</span></p>
                <p id="preview-client">{{ $config?->client_name ?? 'Your Business' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end">
        <button
          type="submit"
          class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          Save Configuration
        </button>
      </div>
    </form>
  </div>
</div>
