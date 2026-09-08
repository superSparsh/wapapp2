<x-layouts.app title="Integration - Shopify - WapApp" active="integration.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col p-4">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="fd-page-title min-w-0 flex-1 text-2xl">Shopify Dashboard</h1>
        <a
          href="{{ route('integration.shopify.scopes') }}"
          class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90"
        >
          <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
          Add Trigger Scopes
        </a>
      </div>
    </div>

    @if (session('success'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <section class="p-4 pt-0">
      @if ($sendData->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-12 text-center">
          <p class="text-sm text-text-muted">No Shopify data yet. Configure your Shopify domain and webhook scopes to start receiving events.</p>
        </div>
      @else
        <x-ui.data-table
          :headers="['SI. No', 'WhatsApp Number', 'Event Type', 'Status', 'Sent At']"
          :paginator="$sendData"
        >
          @foreach ($sendData as $row)
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ $loop->iteration }}</td>
              <td class="fd-table-cell w-[160px] p-2 align-middle">{{ $row->whatsapp_number ?? '—' }}</td>
              <td class="fd-table-cell p-2 align-middle">{{ $row->event_type }}</td>
              <td class="w-[80px] p-2 align-middle">
                @if ($row->status === 'sent')
                  <span class="fd-status-chip inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-green-600">Sent</span>
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
</x-layouts.app>
