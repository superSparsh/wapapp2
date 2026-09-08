@php
  $plan = $summary['plan'] ?? null;
  $subscription = $summary['subscription'] ?? null;
  $expiresAt = $summary['expires_at'] ?? null;
@endphp

<x-profile.layout title="Subscription - WapApp" headerTitle="Subscription" active="profile.subscription">
  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif

  <div class="flex flex-col p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Subscription</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Manage your plan, billing history, and wallet balance. Payments are processed via Razorpay only.
      </p>
    </div>
  </div>

  <section class="flex flex-col gap-4 p-4 pt-0">
    <div class="flex flex-col gap-4 rounded-lg border-[0.5px] border-border-light bg-green-50 p-4 lg:flex-row lg:items-center lg:justify-between">
      <p class="text-sm font-medium leading-[1.4] text-primary-2">
        @if ($plan)
          <span>You are currently subscribed to </span>
          <span class="font-bold text-text-body">{{ $plan->name }}</span>
          <span> plan </span>
          <span class="font-bold text-text-body">(₹ {{ number_format((float) $plan->price, 2) }}).</span>
          @if ($expiresAt)
            <br>
            <span>Your subscription expires on </span>
            <span class="font-bold text-text-body">{{ $expiresAt->format('d M Y') }}</span>
          @endif
        @else
          <span>No active plan selected. Choose a plan to get started.</span>
        @endif
      </p>
      <div class="flex flex-wrap gap-4">
        <a href="{{ route('profile.subscription.upgrade') }}" class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-base font-semibold leading-[1.5] text-primary-2 transition-colors hover:bg-surface">
          <img src="{{ asset('images/profile/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
          Change plan
        </a>
        <a href="{{ route('profile.subscription.manage') }}" class="fd-btn inline-flex items-center justify-center rounded bg-[red] px-4 py-3 text-base font-semibold leading-[1.5] text-white transition-colors hover:opacity-90">
          Cancel Now
        </a>
      </div>
    </div>

    <div class="flex flex-col gap-4 xl:flex-row xl:items-start">
      <div class="flex min-w-0 flex-1 flex-col gap-4 xl:max-w-[746px]">
        <div class="flex flex-col gap-1">
          <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Payment History</h2>
          <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Razorpay subscription and wallet recharge payments.
          </p>
        </div>

        <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left">
              <thead>
                <tr class="bg-elevated">
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Created at</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Purpose</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Amount</th>
                  <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($orders as $order)
                  <tr class="border-t border-divider bg-elevated">
                    <td class="p-2">
                      <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $order->created_at?->format('d M Y') }}</p>
                      <p class="text-[13px] font-normal leading-[1.5] text-text-body">{{ $order->created_at?->format('H:i') }}</p>
                    </td>
                    <td class="p-2 text-[13px] text-text-body">{{ ucfirst(str_replace('_', ' ', $order->purpose->value)) }}</td>
                    <td class="p-2 text-[13px] font-bold text-text-body">₹ {{ number_format((float) $order->total_amount, 2) }}</td>
                    <td class="p-2">
                      <span class="inline-flex rounded px-2 py-1 text-[10px] font-medium {{ $order->status->value === 'paid' ? 'bg-[rgba(16,185,129,0.1)] text-stat-emerald' : 'bg-[rgba(59,130,246,0.1)] text-stat-blue' }}">
                        {{ ucfirst($order->status->value) }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr class="border-t border-divider bg-elevated">
                    <td colspan="4" class="p-4 text-center text-sm text-text-muted">No payments yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex flex-col gap-4">
          <h3 class="text-xl font-semibold leading-[1.4] text-text-primary">Plan details</h3>
          <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
            <div class="grid grid-cols-3 gap-2 bg-elevated p-2">
              <div class="p-2 text-[13px] font-medium text-text-body">Plan name</div>
              <div class="p-2 text-[13px] font-medium text-text-body">Price</div>
              <div class="p-2 text-[13px] font-medium text-text-body">Messages limit</div>
            </div>
            <div class="grid grid-cols-3 gap-2 border-t border-divider bg-elevated px-2 py-1.5">
              <div class="p-2 text-xs text-text-body">{{ $plan?->name ?? '—' }}</div>
              <div class="p-2 text-[13px] font-bold text-text-body">{{ $plan ? '₹ '.number_format((float) $plan->price, 2) : '—' }}</div>
              <div class="p-2 text-xs text-text-body">{{ $plan?->messages_limit ? number_format($plan->messages_limit) : 'Unlimited' }}</div>
            </div>
          </div>
        </div>
      </div>

      <aside class="flex w-full flex-col gap-4 rounded-[20px] bg-elevated p-5 xl:max-w-[380px] xl:shrink-0">
        <div class="flex flex-col gap-1">
          <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Wallet</h2>
          <p class="text-sm text-text-subtle opacity-50">Balance: ₹ {{ number_format($walletBalance, 2) }}</p>
        </div>

        <form id="wallet-recharge-form" class="w-full rounded-xl border border-border-light bg-muted-surface p-4">
          <label for="recharge_amount" class="text-sm font-semibold text-text-primary">Recharge Amount</label>
          <input
            id="recharge_amount"
            name="amount"
            type="number"
            min="{{ config('billing.wallet.min_recharge_amount') }}"
            max="{{ config('billing.wallet.max_recharge_amount') }}"
            value="{{ (int) $rechargeAmount }}"
            class="mt-2 w-full rounded-xl border border-border bg-elevated p-3.5 text-base font-medium text-text-body"
          >
          <div class="mt-4 flex flex-col gap-2 text-sm">
            <div class="flex justify-between"><span class="text-text-muted">GST ({{ $rechargeTotals['gst_rate'] }}%)</span><span>₹ {{ number_format($rechargeTotals['tax'], 2) }}</span></div>
            <div class="flex justify-between font-semibold text-green-700"><span>Total Payable</span><span>₹ {{ number_format($rechargeTotals['total'], 2) }}</span></div>
          </div>
        </form>

        <div class="flex items-center gap-2 rounded-lg border border-border-light bg-elevated p-4">
          <img src="{{ asset('images/profile/razorpay.png') }}" alt="Razorpay" class="size-6">
          <span class="text-sm font-semibold text-text-primary">Razorpay</span>
        </div>

        <x-billing.razorpay-checkout
          button-id="wallet-recharge-btn"
          :checkout-url="route('profile.subscription.wallet-recharge')"
          :verify-url="route('profile.subscription.verify-payment')"
          :button-label="'Pay ₹ '.number_format($rechargeTotals['total'], 2).' Securely'"
          :razorpay-configured="$razorpayConfigured"
        />

        @push('scripts')
          <script>
            document.addEventListener('DOMContentLoaded', () => {
              const input = document.getElementById('recharge_amount');
              const btn = document.getElementById('wallet-recharge-btn');
              if (input && btn) {
                input.addEventListener('input', () => {
                  btn.dataset.amount = input.value;
                });
                btn.dataset.amount = input.value;
              }
            });
          </script>
        @endpush
      </aside>
    </div>
  </section>
</x-profile.layout>
