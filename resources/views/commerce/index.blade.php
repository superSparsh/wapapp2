<x-layouts.app title="Facebook Catalogue - WapApp" active="commerce.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Facebook Catalogue</h1>
        <x-commerce.note />
      </div>

      {{-- Catalog selector --}}
      @if (count($catalogs) > 0)
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-sm font-semibold leading-[1.5] text-primary-2">Select a Catalog:</span>
          <div class="flex flex-wrap gap-2">
            @foreach ($catalogs as $catalog)
              <a
                href="{{ route('commerce.index', ['catalog_id' => $catalog['id']]) }}"
                @class([
                  'inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                  'bg-green-500 text-white' => $catalogId === $catalog['id'],
                  'bg-elevated border border-border text-text-body hover:bg-border' => $catalogId !== $catalog['id'],
                ])
              >
                {{ $catalog['name'] }}
                <span class="ml-1.5 text-xs opacity-70">({{ $catalog['product_count'] }})</span>
              </a>
            @endforeach
          </div>
        </div>
      @endif

      <x-commerce.search-row>
        <a
          href="{{ route('commerce.settings') }}"
          class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          <img src="{{ asset('images/commerce/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          Product Management
        </a>
      </x-commerce.search-row>
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      <x-commerce.catalog-tabs active="products" />

      @if ($error)
        <div class="rounded-lg bg-danger/10 p-4 text-sm text-red-600">{{ $error }}</div>
      @elseif (count($products) > 0)
        <x-ui.data-table
          :headers="['SI. No', 'Image', 'Retailer ID', 'Title', 'Description', 'Brand', 'Condition', 'Availability', 'Price']"
          :total="count($products)"
        >
          @foreach ($products as $index => $product)
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
              <td class="w-[80px] p-2 align-middle">
                @if ($product['image_url'])
                  <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="size-16 rounded object-cover">
                @else
                  <div class="size-16 rounded bg-border"></div>
                @endif
              </td>
              <td class="w-[160px] whitespace-nowrap p-2 align-middle text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $product['retailer_id'] }}</td>
              <td class="fd-table-cell w-[140px] p-2 align-middle">{{ $product['name'] }}</td>
              <td class="fd-table-cell max-w-[188px] p-2 align-middle">
                <span class="line-clamp-2 text-xs">{{ $product['description'] ?: '—' }}</span>
              </td>
              <td class="fd-table-cell p-2 align-middle">{{ $product['brand'] ?: '—' }}</td>
              <td class="p-2 align-middle"><x-commerce.status-badge :label="$product['condition'] ?: 'N/A'" /></td>
              <td class="p-2 align-middle">
                <x-commerce.status-badge
                  :label="$product['availability'] ?: 'N/A'"
                  :variant="str_contains(strtolower($product['availability'] ?? ''), 'in') ? 'green' : 'default'"
                />
              </td>
              <td class="p-2 align-middle text-sm font-semibold text-green-700">{{ $product['price'] }}</td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @elseif ($catalogId)
        <div class="rounded-lg bg-elevated p-8 text-center text-text-subtle">
          No products found for this catalog.
        </div>
      @else
        <div class="rounded-lg bg-elevated p-8 text-center text-text-subtle">
          No catalog connected. Please configure your WhatsApp Business account.
        </div>
      @endif
    </section>
  </div>
</x-layouts.app>
