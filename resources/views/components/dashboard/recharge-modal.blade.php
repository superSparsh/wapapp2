@props([
    'show' => false,
    'closeHref' => null,
    'amount' => 5000,
    'quickAmounts' => [10000, 15000, 20000],
    'rechargeTotals' => null,
    'razorpayConfigured' => false,
])

@php
    $closeUrl = $closeHref ?? url()->current();
    $minAmount = (float) config('billing.wallet.min_recharge_amount', 500);
    $maxAmount = (float) config('billing.wallet.max_recharge_amount', 500000);
    $gstRate = (float) config('billing.gst_rate', 18);
@endphp

@if ($show)
  <div class="fixed inset-0 z-[100] overflow-y-auto overscroll-contain bg-black/60" role="dialog" aria-modal="true" aria-labelledby="recharge-wallet-title">
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
      <div class="my-auto flex w-full max-w-[520px] flex-col gap-6 rounded-[20px] bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
        <div class="flex flex-col gap-3">
          <div class="flex items-center gap-4">
            <h2 id="recharge-wallet-title" class="flex-1 text-2xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">
              Recharge Your Wallet
            </h2>
            <a href="{{ $closeUrl }}" class="relative size-6 shrink-0" aria-label="Close">
              <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
            </a>
          </div>
          <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">
            Add credits securely via Razorpay. GST ({{ number_format($gstRate, 0) }}%) is included in the payable total.
          </p>
        </div>

        <div class="flex flex-col gap-3">
          <label class="fd-label" for="recharge-amount">Enter amount (₹)</label>
          <div class="flex items-center rounded-xl border border-border bg-elevated p-3.5">
            <input
              id="recharge-amount"
              type="number"
              min="{{ $minAmount }}"
              max="{{ $maxAmount }}"
              step="1"
              value="{{ (int) $amount }}"
              class="w-full border-0 bg-transparent p-0 text-sm font-medium leading-[1.4] text-text-primary outline-none"
              style="font-family: var(--font-display)"
            >
          </div>
          <p id="recharge-gst-note" class="text-xs text-text-muted">
            Payable total updates based on the amount entered.
          </p>
        </div>

        <div class="flex flex-wrap gap-4">
          @foreach ($quickAmounts as $quickAmount)
            <button
              type="button"
              class="rounded bg-green-50 px-2 py-1.5 text-xs font-semibold leading-[1.5] text-green-500"
              style="font-family: var(--font-display)"
              data-recharge-amount="{{ $quickAmount }}"
            >
              ₹ {{ number_format($quickAmount) }}
            </button>
          @endforeach
        </div>

        <div class="flex gap-4">
          <a
            href="{{ $closeUrl }}"
            class="fd-btn flex flex-1 items-center justify-center overflow-hidden rounded-xl border-[1.25px] border-green-500 p-3.5 text-center text-base font-extrabold leading-[1.5]"
            style="font-family: var(--font-display); background-image: linear-gradient(179.358deg, rgb(109, 187, 72) 0%, rgba(17, 153, 170, 0.557) 100%); -webkit-background-clip: text; background-clip: text; color: transparent;"
          >
            Cancel
          </a>

          <x-billing.razorpay-checkout
            button-id="dashboard-recharge-btn"
            :checkout-url="route('profile.subscription.wallet-recharge')"
            :verify-url="route('profile.subscription.verify-payment')"
            :redirect-url="route('dashboard', ['status' => 'wallet-recharged'])"
            button-label="Pay Securely"
            :razorpay-configured="$razorpayConfigured"
            class="flex-1"
          />
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const input = document.getElementById('recharge-amount');
      const payBtn = document.getElementById('dashboard-recharge-btn');
      const gstRate = {{ $gstRate }};

      const updatePayable = () => {
        if (!input || !payBtn) return;

        const amount = Number(input.value || 0);
        const tax = Math.round(amount * (gstRate / 100) * 100) / 100;
        const total = Math.round((amount + tax) * 100) / 100;

        payBtn.dataset.amount = String(amount);
        payBtn.textContent = amount > 0 ? `Pay ₹ ${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Securely` : 'Pay Securely';

        const note = document.getElementById('recharge-gst-note');
        if (note && amount > 0) {
          note.textContent = `Amount ₹ ${amount.toLocaleString('en-IN')} + GST ₹ ${tax.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
      };

      document.querySelectorAll('[data-recharge-amount]').forEach((chip) => {
        chip.addEventListener('click', () => {
          if (input) {
            input.value = chip.getAttribute('data-recharge-amount') ?? '';
            updatePayable();
          }
        });
      });

      input?.addEventListener('input', updatePayable);
      updatePayable();
    });
  </script>
@endif
