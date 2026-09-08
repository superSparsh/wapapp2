@props([
    'checkoutUrl',
    'verifyUrl',
    'amount' => null,
    'buttonLabel' => 'Pay Securely',
    'razorpayKey' => null,
    'razorpayConfigured' => false,
    'buttonId' => 'razorpay-pay-btn',
    'redirectUrl' => null,
])

@php
  $key = $razorpayKey ?? config('billing.razorpay.key');
@endphp

@if (! $razorpayConfigured || ! $key)
  <p class="rounded-lg bg-stat-orange/15 p-3 text-sm text-text-body">
    Razorpay is not configured. Add <code>RAZORPAY_KEY</code> and <code>RAZORPAY_SECRET</code> to your <code>.env</code> file.
  </p>
@else
  <button
    type="button"
    id="{{ $buttonId }}"
    data-checkout-url="{{ $checkoutUrl }}"
    data-verify-url="{{ $verifyUrl }}"
    data-redirect-url="{{ $redirectUrl ?? route('profile.subscription') }}"
    data-key="{{ $key }}"
    data-amount="{{ $amount }}"
    class="razorpay-checkout-btn fd-btn flex w-full items-center justify-center overflow-hidden rounded-xl p-3.5 text-center text-base font-extrabold leading-[1.5] text-white transition-opacity hover:opacity-90"
    style="background: linear-gradient(179.23deg, #6dbb48 0%, rgba(17, 153, 170, 0.557) 100%);"
  >
    {{ $buttonLabel }}
  </button>

  @once
    @push('scripts')
      <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          document.querySelectorAll('.razorpay-checkout-btn').forEach((btn) => {
          const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

          btn.addEventListener('click', async () => {
            try {
              const checkoutRes = await fetch(btn.dataset.checkoutUrl, {
                method: 'POST',
                headers: {
                  'X-CSRF-TOKEN': csrf,
                  'Accept': 'application/json',
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                  amount: btn.dataset.amount ? Number(btn.dataset.amount) : undefined,
                }),
              });

              const checkout = await checkoutRes.json();
              if (!checkoutRes.ok) {
                await (window.showAppAlert?.(checkout.message || 'Unable to start payment.', 'Payment') ?? Promise.resolve());
                return;
              }

              const options = {
                key: checkout.key,
                amount: checkout.amount,
                currency: checkout.currency,
                order_id: checkout.order_id,
                name: 'WapApp',
                handler: async function (response) {
                  const verifyRes = await fetch(btn.dataset.verifyUrl, {
                    method: 'POST',
                    headers: {
                      'X-CSRF-TOKEN': csrf,
                      'Accept': 'application/json',
                      'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                      razorpay_order_id: response.razorpay_order_id,
                      razorpay_payment_id: response.razorpay_payment_id,
                      razorpay_signature: response.razorpay_signature,
                      redirect_url: btn.dataset.redirectUrl,
                    }),
                  });

                  if (verifyRes.ok) {
                    const data = await verifyRes.json().catch(() => ({}));
                    window.location.href = data.redirect || btn.dataset.redirectUrl;
                  } else {
                    const data = await verifyRes.json();
                    await (window.showAppAlert?.(data.message || 'Payment verification failed.', 'Payment') ?? Promise.resolve());
                  }
                },
              };

              new Razorpay(options).open();
            } catch (e) {
              await (window.showAppAlert?.('Payment could not be started.', 'Payment') ?? Promise.resolve());
            }
          });
          });
        });
      </script>
    @endpush
  @endonce
@endif
