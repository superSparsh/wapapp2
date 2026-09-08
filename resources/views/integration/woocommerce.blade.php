<x-layouts.app title="WooCommerce - WapApp" active="integration.index">
  <div class="flex flex-col bg-surface">
    <div class="p-4">
      <x-ui.page-header title="WooCommerce" subtitle="Connect WooCommerce stores for order and customer triggers." />
    </div>

    @if (session('status'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <section class="grid gap-4 p-4 pt-0 lg:grid-cols-[360px_1fr]">
      <form method="post" action="{{ route('integration.woocommerce.store') }}" class="flex flex-col gap-4 rounded-xl border border-border bg-elevated p-5">
        @csrf
        <h2 class="fd-card-title">Connect Store</h2>

        <div class="flex flex-col gap-1">
          <x-form.label for="store_url">Store URL</x-form.label>
          <x-form.input id="store_url" name="store_url" type="url" placeholder="https://shop.example.com" required />
        </div>

        <div class="flex flex-col gap-1">
          <x-form.label for="consumer_key">Consumer Key</x-form.label>
          <x-form.input id="consumer_key" name="consumer_key" />
        </div>

        <div class="flex flex-col gap-1">
          <x-form.label for="consumer_secret">Consumer Secret</x-form.label>
          <x-form.input id="consumer_secret" name="consumer_secret" type="password" />
        </div>

        <button type="submit" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">
          Connect
        </button>
      </form>

      <div class="rounded-xl border border-border bg-elevated p-5">
        <h2 class="fd-card-title mb-4">Connected Stores</h2>

        @if ($stores->isEmpty())
          <p class="text-sm text-text-muted">No WooCommerce stores connected yet.</p>
        @else
          <x-ui.data-table :headers="['Store URL', 'Status', 'Actions']" :total="$stores->count()">
            @foreach ($stores as $store)
              <tr class="bg-elevated">
                <td class="fd-table-cell p-2 align-middle">{{ $store->store_url }}</td>
                <td class="fd-table-cell p-2 align-middle">{{ $store->status }}</td>
                <td class="fd-table-cell p-2 align-middle">
                  <form method="post" action="{{ route('integration.woocommerce.destroy', $store) }}" onsubmit="return confirm('Remove this store?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:underline">Remove</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </x-ui.data-table>
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
