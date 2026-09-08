<x-layouts.app title="Payment Dashboard - WapApp" active="commerce.settings">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Payment Dashboard</h1>
        <x-commerce.note />
      </div>

      {{-- Success / Error flash --}}
      @if (session('success'))
        <div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
      @endif
      @error('payment')
        <div class="rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ $message }}</div>
      @enderror

      <x-commerce.search-row :show-refresh="false">
        <button
          type="button"
          data-open-modal="payment-configuration"
          class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          Payment Configuration
        </button>
        <button
          type="button"
          data-open-modal="create-payment"
          class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          <img src="{{ asset('images/commerce/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          Create New Payment
        </button>
      </x-commerce.search-row>

      {{-- Stats cards --}}
      <div class="grid gap-4 md:grid-cols-2">
        <div class="flex flex-col gap-5 rounded-xl border border-green-500 bg-green-50 p-5">
          <div class="flex size-12 items-center justify-center overflow-hidden rounded-xl bg-green-500 p-3">
            <img src="{{ asset('images/commerce/graph.svg') }}" alt="" class="size-6" width="24" height="24">
          </div>
          <div class="flex items-end justify-between gap-5">
            <div class="min-w-0 flex-1">
              <p class="text-base font-medium leading-[1.4] text-text-primary">Total Paid</p>
              <p class="mt-2 text-2xl font-bold leading-[38px] text-text-primary">
                ₹ {{ number_format($stats['total_paid'], 2) }}
              </p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 py-0.5 pl-2 pr-2.5 text-sm font-medium leading-5 text-green-700">
              {{ $stats['paid_count'] }} payment(s)
            </span>
          </div>
        </div>

        <div class="flex flex-col gap-5 rounded-xl border border-[orange] bg-[rgba(255,165,0,0.1)] p-5">
          <div class="flex size-12 items-center justify-center overflow-hidden rounded-xl bg-[orange] p-3">
            <img src="{{ asset('images/commerce/danger.svg') }}" alt="" class="size-6" width="24" height="24">
          </div>
          <div class="flex items-end justify-between gap-5">
            <div class="min-w-0 flex-1">
              <p class="text-base font-medium leading-[1.4] text-text-primary">Total Pending</p>
              <p class="mt-2 text-2xl font-bold leading-[38px] text-text-primary">
                ₹ {{ number_format($stats['total_pending'], 2) }}
              </p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-danger/10 py-0.5 pl-2 pr-2.5 text-sm font-medium leading-5 text-[red]">
              {{ $stats['pending_count'] }} pending
            </span>
          </div>
        </div>
      </div>

      {{-- Active config display --}}
      @if ($config)
        <div class="rounded-lg bg-elevated p-3">
          <h2 class="mb-4 text-xl font-semibold leading-[1.5] text-text-primary">Payment Configuration</h2>
          <div class="overflow-hidden rounded-xl border border-divider shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
            <div class="grid grid-cols-3 gap-2 bg-elevated p-2">
              <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Client Name:</div>
              <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Razorpay Key:</div>
              <div class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Selected Template:</div>
            </div>
            <div class="grid grid-cols-3 gap-2 border-t border-divider bg-elevated px-2 py-1.5 text-xs font-normal leading-[1.5] text-text-body">
              <div class="p-2">{{ $config->client_name }}</div>
              <div class="p-2 font-mono">{{ Str::mask($config->razorpay_key, '*', 8) }}</div>
              <div class="p-2">
                @if ($config->payment_template_id)
                  {{ $templates->firstWhere('id', $config->payment_template_id)?->name ?? '—' }}
                @else
                  <span class="text-text-subtle">Not set</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="rounded-lg border border-dashed border-border bg-elevated p-5 text-center text-sm text-text-subtle">
          No payment configuration yet. Click <strong>Payment Configuration</strong> to set up your Razorpay credentials.
        </div>
      @endif
    </div>

    {{-- Transactions table --}}
    <section class="flex flex-col gap-4 p-4 pt-0">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold leading-[1.5] text-text-primary">Recent Transactions</h2>
        <a
          href="{{ route('commerce.settings') }}?export=1"
          class="fd-btn inline-flex items-center justify-center gap-3 rounded-lg border border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
        >
          <img src="{{ asset('images/commerce/export-excel.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
          Export to Excel
        </a>
      </div>

      @if ($payments->isEmpty())
        <div class="rounded-lg bg-elevated p-8 text-center text-text-subtle">No transactions yet.</div>
      @else
        <x-ui.data-table
          :headers="['SI. No', 'Order Ref', 'Customer Name', 'Customer Phone', 'Amount', 'Currency', 'Razorpay Ref ID', 'Status', 'Payment Link', 'Created At (IST)']"
          :paginator="$payments"
        >
          @foreach ($payments as $index => $payment)
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad($payments->firstItem() + $index, 2, '0', STR_PAD_LEFT) }}</td>
              <td class="w-[120px] whitespace-nowrap p-2 align-middle text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $payment->internal_order_ref }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $payment->customer_name }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $payment->customer_phone }}</td>
              <td class="w-[100px] p-2 align-middle text-sm font-semibold text-green-700">₹ {{ number_format((float) $payment->amount, 2) }}</td>
              <td class="fd-table-cell w-[80px] p-2 align-middle">{{ $payment->currency }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $payment->razorpay_payment_id ?? $payment->razorpay_payment_link_id ?? '—' }}</td>
              <td class="w-[80px] p-2 align-middle">
                <x-commerce.status-badge
                  :label="$payment->status->label()"
                  :variant="$payment->status->color()"
                />
              </td>
              <td class="p-2 align-middle">
                @if ($payment->payment_link)
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      onclick="navigator.clipboard.writeText('{{ $payment->payment_link }}').then(() => this.textContent = 'Copied!')"
                      class="text-[13px] font-normal leading-[1.5] text-green-500 underline"
                    >Copy Link</button>
                    <img src="{{ asset('images/commerce/document-copy.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
                  </div>
                @else
                  <span class="text-xs text-text-subtle">—</span>
                @endif
              </td>
              <td class="fd-table-cell p-2 align-middle">{{ $payment->created_at?->setTimezone('Asia/Kolkata')->format('d/m/Y, g:i:s a') }}</td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @endif
    </section>
  </div>

  {{-- Modals (wired to real forms) --}}
  <x-commerce.create-payment-modal :action="route('commerce.payments.create')" />
  <x-commerce.payment-configuration-modal
    :action="route('commerce.settings.save')"
    :config="$config"
    :templates="$templates"
  />
</x-layouts.app>
