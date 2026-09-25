<x-layouts.app title="Facebook Catalogue - Products - WapApp" active="commerce.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Facebook Catalogue</h1>
        <x-commerce.note />
      </div>

      <x-commerce.sub-nav />

      @if (count($catalogs) > 0)
        <div class="flex w-full max-w-[320px] flex-col gap-2">
          <label for="catalog_id" class="text-sm font-semibold leading-[1.4] text-text-primary">Select a Catalog:</label>
          <x-ui.select
            id="catalog_id"
            variant="default"
            class="w-full"
            aria-label="Select a catalog"
            onchange="window.location.href=this.value"
          >
            @foreach ($catalogs as $catalog)
              <option
                value="{{ route('commerce.index', ['catalog_id' => $catalog['id']]) }}"
                @selected((string) $catalogId === (string) $catalog['id'])
              >
                {{ $catalog['name'] }} ({{ number_format((int) $catalog['product_count']) }})
              </option>
            @endforeach
          </x-ui.select>
        </div>
      @endif

      <x-commerce.search-row>
        <a
          href="https://business.facebook.com/commerce"
          target="_blank"
          rel="noopener noreferrer"
          class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
        >
          <img src="{{ asset('images/commerce/add.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          Product Management
        </a>
      </x-commerce.search-row>
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      @if ($error)
        <div class="rounded-lg bg-danger/10 p-4 text-sm text-red-600">{{ $error }}</div>
      @elseif (count($products) > 0)
        <x-ui.data-table
          :headers="['SI. No', 'Image', 'Retailer ID', 'Title', 'Description', 'Brand', 'Condition', 'Availability', 'Price']"
          :total="count($products)"
        >
          @foreach ($products as $index => $product)
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
              <td class="w-[80px] p-2 align-middle">
                @if ($product['image_url'])
                  <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" class="size-16 rounded object-cover">
                @else
                  <div class="size-16 rounded bg-border"></div>
                @endif
              </td>
              <td class="w-[160px] whitespace-nowrap p-2 align-middle text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $product['retailer_id'] ?: '—' }}</td>
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
