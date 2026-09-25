<x-layouts.app title="Integration - Shopify - WapApp" active="integration.index">
  @php
    $domainUrl = $integration->domainUrl();
  @endphp
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col p-4">
      <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
          <h1 class="fd-page-title text-2xl">Shopify Dashboard</h1>
          <p class="mt-1 text-sm text-text-muted">
            @if ($domainUrl)
              Store: <span class="font-medium text-text-primary">{{ $domainUrl }}</span>
            @else
              Connect your Shopify store domain to start receiving webhooks.
            @endif
          </p>
        </div>
        <button type="button" data-open-modal="shopify-domain"
          class="fd-btn inline-flex shrink-0 items-center justify-center rounded border border-border bg-elevated px-4 py-3 text-sm font-semibold text-text-primary hover:bg-surface">
          {{ $domainUrl ? 'Edit Domain' : 'Connect Store' }}
        </button>
        <a
          href="{{ route('integration.shopify.scopes') }}"
          class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90"
        >
          <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
          Add Trigger Scopes
        </a>
      </div>

      <div class="mt-4">
        <x-ui.listing-toolbar
          :action="route('integration.index')"
          :show-sort="true"
          :search-value="''"
          search-placeholder="Search"
          :current-sort="$currentSort ?? 'created_at'"
          :current-direction="$currentDirection ?? 'desc'"
          :sort-options="[
            ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
            ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
            ['value' => 'sent_at', 'label' => 'Sent date', 'direction' => 'desc'],
            ['value' => 'event_type', 'label' => 'Event type A–Z', 'direction' => 'asc'],
            ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
            ['value' => 'whatsapp_number', 'label' => 'Phone A–Z', 'direction' => 'asc'],
          ]"
        />
      </div>
    </div>

    @if (session('success'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <section class="p-4 pt-0">
      @if ($sendData->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-12 text-center">
          <p class="text-sm text-text-muted">
            No Shopify message logs yet.
            @if (! $domainUrl)
              Connect your store domain, then add webhook scopes.
            @else
              Configure webhook scopes and point Shopify Admin webhooks to this app.
            @endif
          </p>
        </div>
      @else
        <x-ui.data-table
          :headers="['SI. No', 'WhatsApp Number', 'Event Type', 'Template', 'Status', 'Sent At']"
          :paginator="$sendData"
        >
          @foreach ($sendData as $row)
            @php
              $payload = is_array($row->payload) ? $row->payload : [];
              $templateName = $payload['template_name'] ?? null;
              $error = $payload['error'] ?? $payload['reason'] ?? null;
            @endphp
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ $loop->iteration }}</td>
              <td class="fd-table-cell w-[160px] p-2 align-middle">{{ $row->whatsapp_number ?? '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $row->event_type }}</td>
              <td class="fd-table-cell p-2 align-middle">
                <span class="block truncate" title="{{ $templateName }}">{{ $templateName ?? '—' }}</span>
                @if ($error)
                  <span class="mt-0.5 block truncate text-[10px] text-red-500" title="{{ $error }}">{{ $error }}</span>
                @endif
              </td>
              <td class="w-[90px] p-2 align-middle">
                @if ($row->status === 'sent')
                  <span class="fd-status-chip inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-green-600">Sent</span>
                @elseif ($row->status === 'skipped')
                  <span class="fd-status-chip inline-flex items-center justify-center rounded bg-yellow-100 px-2 py-1 text-[10px] font-medium leading-[1.2] text-yellow-700">Skipped</span>
                @else
                  <span class="fd-status-chip inline-flex items-center justify-center rounded bg-red-100 px-2 py-1 text-[10px] font-medium leading-[1.2] text-red-600">Failed</span>
                @endif
              </td>
              <td class="fd-table-cell w-[160px] whitespace-nowrap p-2 align-middle">{{ $row->sent_at?->format('Y-m-d H:i') ?? '—' }}</td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @endif
    </section>
  </div>

  {{-- Domain modal --}}
  <div id="modal-shopify-domain" data-modal="shopify-domain"
    class="fixed inset-0 z-50 {{ $domainUrl ? 'hidden' : 'flex' }} items-center justify-center bg-black/60 p-4"
    role="dialog" aria-modal="true" aria-labelledby="modal-title-shopify-domain">
    <div class="flex w-full max-w-[520px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
      <div class="flex items-start gap-4">
        <div class="min-w-0 flex-1">
          <h2 id="modal-title-shopify-domain" class="text-2xl font-bold leading-[1.5] text-text-primary">
            {{ $domainUrl ? 'Update Shopify Domain' : 'Connect Shopify Store' }}
          </h2>
          <p class="mt-1 text-sm text-text-subtle opacity-50">
            Enter your store URL (e.g. https://your-store.myshopify.com)
          </p>
        </div>
        @if ($domainUrl)
          <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
            <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
          </button>
        @endif
      </div>

      <form id="shopify-domain-form" class="flex flex-col gap-4">
        @csrf
        <div class="rounded-xl border border-border-light bg-muted-surface p-4">
          <label for="shopify_domainurl" class="mb-2 block text-sm font-semibold text-text-primary">
            Shopify Domain URL <span class="text-red-500">*</span>
          </label>
          <input
            id="shopify_domainurl"
            name="domainurl"
            type="url"
            required
            value="{{ $domainUrl }}"
            placeholder="https://your-store.myshopify.com"
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none"
          >
          <p class="mt-2 text-xs text-text-muted">
            After saving, create matching webhooks in Shopify Admin pointing to this app’s webhook URL.
          </p>
        </div>
        <p id="shopify-domain-status" class="hidden text-sm font-medium" role="status"></p>
        <div class="flex justify-end gap-3">
          @if ($domainUrl)
            <button type="button" data-modal-close
              class="fd-btn rounded border border-border px-4 py-3 text-sm font-semibold text-text-primary hover:bg-surface">
              Cancel
            </button>
          @endif
          <button type="submit" id="shopify-domain-submit"
            class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 hover:opacity-90 disabled:opacity-60">
            Save Domain
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const form = document.getElementById('shopify-domain-form');
      const statusEl = document.getElementById('shopify-domain-status');
      const submitBtn = document.getElementById('shopify-domain-submit');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || form?.querySelector('[name="_token"]')?.value;

      form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const domainurl = document.getElementById('shopify_domainurl')?.value?.trim();
        if (!domainurl) return;

        submitBtn.disabled = true;
        if (statusEl) {
          statusEl.textContent = 'Saving…';
          statusEl.className = 'text-sm font-medium text-text-muted';
          statusEl.classList.remove('hidden');
        }

        try {
          const res = await fetch(@json(route('integration.shopify.domain.store')), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ domainurl }),
          });
          const data = await res.json();
          if (!res.ok || !data.success) {
            if (statusEl) {
              statusEl.textContent = data.message || Object.values(data.errors || {})[0]?.[0] || 'Failed to save domain.';
              statusEl.className = 'text-sm font-medium text-red-600';
            }
            submitBtn.disabled = false;
            return;
          }

          window.showSuccessToast?.(data.message || 'Shopify domain saved.');
          setTimeout(() => location.reload(), 500);
        } catch (e) {
          if (statusEl) {
            statusEl.textContent = 'Request failed. Please try again.';
            statusEl.className = 'text-sm font-medium text-red-600';
          }
          submitBtn.disabled = false;
        }
      });
    })();
  </script>
</x-layouts.app>
