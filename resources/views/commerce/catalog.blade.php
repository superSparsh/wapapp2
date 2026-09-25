<x-layouts.app title="Facebook Catalogue - Catalogues - WapApp" active="commerce.catalog">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Facebook Catalogue</h1>
        <x-commerce.note />
      </div>

      <x-commerce.sub-nav />

      <x-commerce.search-row />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      @if ($error)
        <div class="rounded-lg bg-danger/10 p-4 text-sm text-red-600">{{ $error }}</div>
      @elseif (count($catalogs) > 0)
        <x-ui.data-table
          :headers="['SI. No', 'Catalogue ID', 'Facebook Business ID', 'Catalogue Name', 'Vertical', 'Products', 'Status']"
          :total="count($catalogs)"
        >
          @foreach ($catalogs as $index => $catalog)
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
              <td class="w-[160px] whitespace-nowrap p-2 align-middle text-[13px] font-semibold leading-[1.5] text-text-subtle">
                {{ $catalog['id'] ?: '—' }}
              </td>
              <td class="fd-table-cell w-[180px] p-2 align-middle">{{ $catalog['business_id'] ?: '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">
                <div class="flex items-center gap-2">
                  @if (! empty($catalog['image_url']))
                    <img src="{{ $catalog['image_url'] }}" alt="" class="size-8 rounded object-cover" width="32" height="32">
                  @endif
                  <span>{{ $catalog['name'] }}</span>
                </div>
              </td>
              <td class="fd-table-cell p-2 align-middle">{{ $catalog['vertical'] ?: '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ number_format((int) $catalog['product_count']) }}</td>
              <td class="p-2 align-middle">
                <x-commerce.status-badge label="Connected" variant="green" />
              </td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @else
        <div class="rounded-lg bg-elevated p-8 text-center text-text-subtle">
          No catalogues found. Connect a Facebook catalogue to your WhatsApp Business account.
        </div>
      @endif
    </section>
  </div>
</x-layouts.app>
