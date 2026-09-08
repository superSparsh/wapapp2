@php
    $rows = [
        'Paid',
        'Paid',
        'Pending',
        'Pending',
        'Paid',
        'Paid',
        'Pending',
        'Paid',
        'Paid',
        'Pending',
    ];
@endphp

<x-layouts.app title="Order Details - WapApp" active="commerce.catalog">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Whatsapp Orders</h1>
        <x-commerce.note />
      </div>

      <x-commerce.search-row />
    </div>

    <section class="p-4 pt-0">
      <x-ui.data-table
        :headers="['SI. No', 'Customer Name', 'Date', 'Customer Phone', 'Total', 'Items', 'Order Status', 'Payment Status']"
        :total="25"
      >
        @foreach ($rows as $paymentStatus)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2 align-middle">01</td>
            <td class="w-[180px] whitespace-nowrap p-2 align-middle">
              <button type="button" data-open-modal="order-details" class="text-[13px] font-semibold leading-[1.5] text-text-subtle hover:underline">Customer Name</button>
            </td>
            <td class="fd-table-cell w-[160px] p-2 align-middle">08-01-2026 04:15 PM</td>
            <td class="fd-table-cell w-[188px] p-2 align-middle">25896 69852</td>
            <td class="p-2 align-middle text-sm font-semibold text-green-700">₹ 5,900</td>
            <td class="fd-table-cell p-2 align-middle">1</td>
            <td class="p-2 align-middle"><x-commerce.status-badge label="New" /></td>
            <td class="p-2 align-middle">
              <x-commerce.status-badge
                :label="$paymentStatus"
                :variant="$paymentStatus === 'Paid' ? 'green' : 'orange'"
              />
            </td>
          </tr>
        @endforeach
      </x-ui.data-table>
    </section>
  </div>

  <x-commerce.order-details-modal :open="true" :close-href="route('commerce.catalog')" />
</x-layouts.app>
