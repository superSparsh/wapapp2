<x-admin.layout title="Wallet payment - Admin" active="admin.wallet-payments.index">
  @php
    $steps = $detail['steps'] ?? [];
    $stateClass = [
      'done' => 'bg-green-50 text-green-700 ring-green-200',
      'active' => 'bg-amber-50 text-amber-700 ring-amber-200',
      'error' => 'bg-red-50 text-red-700 ring-red-200',
      'pending' => 'bg-surface text-text-subtle ring-border',
    ];
  @endphp

  <div class="p-4">
    <a href="{{ route('admin.wallet-payments.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Wallet payments</a>
    <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-text-primary">{{ $detail['tenant_name'] }}</h1>
        <p class="text-sm text-text-subtle opacity-70">{{ $detail['currency'] }} {{ $detail['amount'] }} · {{ $detail['razorpay_order_id'] }}</p>
        <div class="mt-2">
          <x-admin.status-badge :status="$detail['journey_status']" :label="$detail['journey_label']" />
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        @if (! empty($detail['can_complete_from_razorpay']))
          <form method="POST" action="{{ route('admin.wallet-payments.complete', [$detail['tenant_id'], $detail['order_uuid']]) }}">
            @csrf
            <button type="submit" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700"
              onclick="return confirm('Fetch captured payment from Razorpay and credit wallet?')">
              Complete from Razorpay
            </button>
          </form>
        @endif
        @if (! empty($detail['can_retry_zoho']))
          <form method="POST" action="{{ route('admin.wallet-payments.retry-zoho', [$detail['tenant_id'], $detail['order_uuid']]) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-border bg-elevated px-3 py-2 text-xs font-semibold text-text-primary hover:bg-surface"
              onclick="return confirm('Queue Zoho invoice sync again?')">
              Retry Zoho
            </button>
          </form>
        @endif
        <a href="{{ route('admin.customers.show', $detail['tenant_id']) }}" class="rounded-lg border border-border bg-elevated px-3 py-2 text-xs font-semibold text-text-primary hover:bg-surface">Open customer</a>
      </div>
    </div>
  </div>

  <section class="mx-4 mb-4 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    <h2 class="text-lg font-bold text-text-primary">Journey</h2>
    <ol class="mt-4 space-y-3">
      @foreach ($steps as $step)
        <li class="flex gap-3">
          <span @class([
            'mt-0.5 inline-flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-[10px] font-bold uppercase ring-1 ring-inset',
            $stateClass[$step['state']] ?? $stateClass['pending'],
          ])>{{ $loop->iteration }}</span>
          <div>
            <p class="text-sm font-semibold text-text-primary">{{ $step['label'] }}</p>
            <p class="text-xs text-text-subtle">{{ $step['detail'] }}</p>
          </div>
        </li>
      @endforeach
    </ol>
  </section>

  <section class="mx-4 mb-4 grid max-w-3xl gap-4 sm:grid-cols-2">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-sm font-bold text-text-primary">Razorpay order</h2>
      <dl class="mt-3 space-y-2 text-sm">
        @foreach ([
          'Order id' => $detail['order']['razorpay_order_id'] ?? '',
          'Payment id' => $detail['order']['razorpay_payment_id'] ?? '',
          'Status' => $detail['order']['status'] ?? '',
          'Credits' => ($detail['order']['currency'] ?? '').' '.($detail['order']['amount'] ?? ''),
          'Tax' => ($detail['order']['currency'] ?? '').' '.($detail['order']['tax_amount'] ?? ''),
          'Paid total' => ($detail['order']['currency'] ?? '').' '.($detail['order']['total_amount'] ?? ''),
          'Created' => format_ist($detail['order']['created_at'] ?? null),
          'Paid at' => format_ist($detail['order']['paid_at'] ?? null),
        ] as $label => $value)
          <div>
            <dt class="text-xs uppercase tracking-wide text-text-subtle">{{ $label }}</dt>
            <dd class="mt-0.5 font-semibold break-all">{{ $value ?: '—' }}</dd>
          </div>
        @endforeach
      </dl>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-sm font-bold text-text-primary">Wallet & Zoho</h2>
      <dl class="mt-3 space-y-2 text-sm">
        <div>
          <dt class="text-xs uppercase tracking-wide text-text-subtle">Wallet credit</dt>
          <dd class="mt-0.5 font-semibold">
            @if ($detail['wallet'])
              {{ $detail['wallet']['amount'] }} · {{ format_ist($detail['wallet']['created_at']) }}
            @else
              Not credited
            @endif
          </dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-text-subtle">Zoho status</dt>
          <dd class="mt-0.5 font-semibold">{{ $detail['zoho']['status'] ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-xs uppercase tracking-wide text-text-subtle">Invoice</dt>
          <dd class="mt-0.5 font-semibold">{{ $detail['zoho']['invoice_number'] ?? '—' }}</dd>
        </div>
        @if (! empty($detail['zoho']['last_error']))
          <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600">{{ $detail['zoho']['last_error'] }}</div>
        @endif
        @if (! empty($detail['zoho']['uuid']))
          <a href="{{ route('admin.zoho-credits.show', $detail['zoho']['uuid']) }}" class="inline-block text-xs font-semibold text-green-600 hover:underline">Open Zoho credit record</a>
        @endif
      </dl>
    </div>
  </section>
</x-admin.layout>
