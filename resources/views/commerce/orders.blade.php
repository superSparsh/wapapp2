<x-layouts.app title="Orders - WapApp" active="commerce.orders">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">WhatsApp Orders</h1>
        <x-commerce.note />
      </div>

      {{-- Filters row --}}
      <form method="GET" action="{{ route('commerce.orders') }}" class="flex flex-wrap items-center gap-3">
        <div class="flex w-full max-w-[380px] items-center gap-3 rounded-lg bg-elevated p-3">
          <x-icons.nav-icon name="search" class="size-5 shrink-0 text-text-body/60" />
          <input
            type="search"
            name="q"
            value="{{ request('q') }}"
            placeholder="Search by name, phone, catalog ID…"
            class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none"
          >
        </div>

        <select name="order_status" class="rounded-lg border border-border bg-elevated px-3 py-2.5 text-sm text-text-body focus:outline-none">
          <option value="">All Order Status</option>
          @foreach (\App\Domains\Commerce\Enums\OrderStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(request('order_status') === $status->value)>{{ $status->label() }}</option>
          @endforeach
        </select>

        <select name="payment_status" class="rounded-lg border border-border bg-elevated px-3 py-2.5 text-sm text-text-body focus:outline-none">
          <option value="">All Payment Status</option>
          @foreach (\App\Domains\Commerce\Enums\PaymentStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(request('payment_status') === $status->value)>{{ $status->label() }}</option>
          @endforeach
        </select>

        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
          Filter
        </button>
        @if (request()->hasAny(['q', 'order_status', 'payment_status']))
          <a href="{{ route('commerce.orders') }}" class="text-sm text-text-subtle underline">Clear</a>
        @endif
      </form>

      {{-- Stats bar --}}
      <div class="flex flex-wrap gap-3 text-sm">
        <span class="rounded-full bg-elevated px-3 py-1 text-text-body">Total: <strong>{{ $stats['total'] }}</strong></span>
        <span class="rounded-full bg-blue-50 px-3 py-1 text-blue-700">New: <strong>{{ $stats['new'] }}</strong></span>
        <span class="rounded-full bg-purple-50 px-3 py-1 text-purple-700">Confirmed: <strong>{{ $stats['confirmed'] }}</strong></span>
        <span class="rounded-full bg-green-50 px-3 py-1 text-green-700">Delivered: <strong>{{ $stats['delivered'] }}</strong></span>
        <span class="rounded-full bg-red-50 px-3 py-1 text-red-600">Cancelled: <strong>{{ $stats['cancelled'] }}</strong></span>
      </div>
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      @if ($orders->isEmpty())
        <div class="rounded-lg bg-elevated p-8 text-center text-text-subtle">No orders found.</div>
      @else
        <x-ui.data-table
          :headers="['SI. No', 'Customer', 'Phone', 'Products', 'Total', 'Order Status', 'Payment Status', 'Payment Link', 'Date']"
          :paginator="$orders"
        >
          @foreach ($orders as $index => $order)
            <tr
              class="cursor-pointer bg-elevated hover:bg-border/30"
              data-open-modal="order-details"
              data-order-uuid="{{ $order->uuid }}"
            >
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad($orders->firstItem() + $index, 2, '0', STR_PAD_LEFT) }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $order->customer_name ?? '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $order->customer_phone ?? '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ count($order->product_items ?? []) }} item(s)</td>
              <td class="p-2 align-middle text-sm font-semibold text-green-700">
                {{ $order->currency }} {{ number_format((float) $order->total_price, 2) }}
              </td>
              <td class="p-2 align-middle">
                <x-commerce.status-badge
                  :label="$order->order_status->label()"
                  :variant="$order->order_status->color()"
                />
              </td>
              <td class="p-2 align-middle">
                <x-commerce.status-badge
                  :label="$order->payment_status->label()"
                  :variant="$order->payment_status->color()"
                />
              </td>
              <td class="p-2 align-middle">
                @if ($order->payment_link)
                  <a href="{{ $order->payment_link }}" target="_blank" class="text-[13px] text-green-500 underline" onclick="event.stopPropagation()">
                    Copy Link
                  </a>
                @else
                  <span class="text-xs text-text-subtle">—</span>
                @endif
              </td>
              <td class="fd-table-cell p-2 align-middle">{{ $order->created_at?->format('d/m/Y, g:i a') }}</td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @endif
    </section>
  </div>

  {{-- Order details modal (populated via JS fetch) --}}
  <x-commerce.order-details-modal />
</x-layouts.app>
