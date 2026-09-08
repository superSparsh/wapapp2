<x-layouts.app title="Webhook Logs - WapApp" active="webhooks.logs">
  <div class="flex flex-col bg-surface">
    {{-- Flash message --}}
    @if (session('status'))
      <div class="mx-4 mt-3 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Webhook Logs</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Monitor your webhook delivery status and responses
      </p>
    </div>

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      {{-- Metrics Cards --}}
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
          $metricCards = [
            ['Total', $metrics['total'], 'border-border'],
            ['Successful', $metrics['successful'], 'border-[green]'],
            ['Pending', $metrics['pending'], 'border-[orange]'],
            ['Failed', $metrics['failed'], 'border-[rgba(255,0,0,0.5)]'],
          ];
        @endphp
        @foreach ($metricCards as [$label, $value, $border])
          <div @class([
            'flex flex-col overflow-hidden rounded-xl border border-solid bg-elevated p-5',
            $border,
          ])>
            <div class="flex w-full items-end gap-5">
              <div class="flex min-w-0 flex-1 flex-col gap-2">
                <p class="text-base font-medium leading-[1.4] whitespace-nowrap text-text-primary">{{ $label }}</p>
                <p class="text-2xl font-bold leading-[38px] text-text-primary">{{ number_format($value) }}</p>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Filters --}}
      <form action="{{ route('webhooks.logs') }}" method="GET" class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <div class="flex min-w-0 flex-1 items-center overflow-hidden rounded-lg bg-elevated p-3">
          <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search by event, payload, or error"
            class="w-full bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body placeholder:opacity-40 focus:outline-none"
          >
        </div>

        <div class="relative min-w-0 flex-1">
          <select name="status" class="w-full appearance-none rounded-lg bg-elevated p-3 pr-10 text-sm font-medium leading-[1.4] text-text-body focus:outline-none">
            <option value="">All Statuses</option>
            @foreach (['sent' => 'Successful', 'failed' => 'Failed', 'pending' => 'Pending', 'retrying' => 'Retrying'] as $val => $label)
              <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
            @endforeach
          </select>
          <img
            src="{{ asset('images/webhooks/arrow-down.svg') }}"
            alt=""
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
            width="16" height="16"
          >
        </div>

        <div class="relative min-w-0 flex-1">
          <select name="subscription_id" class="w-full appearance-none rounded-lg bg-elevated p-3 pr-10 text-sm font-medium leading-[1.4] text-text-body focus:outline-none">
            <option value="">All Webhooks</option>
            @foreach ($subscriptions as $sub)
              <option value="{{ $sub->id }}" @selected(request('subscription_id') == $sub->id)>{{ $sub->description }}</option>
            @endforeach
          </select>
          <img
            src="{{ asset('images/webhooks/arrow-down.svg') }}"
            alt=""
            class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2"
            width="16" height="16"
          >
        </div>

        <div class="flex min-w-0 flex-1 items-center justify-between rounded-lg bg-elevated p-3">
          <input
            type="date"
            name="date_from"
            value="{{ request('date_from') }}"
            class="w-full bg-transparent text-sm font-medium leading-[1.4] text-text-body focus:outline-none"
            placeholder="Date From"
          >
        </div>

        <div class="flex min-w-0 flex-1 items-center justify-between rounded-lg bg-elevated p-3">
          <input
            type="date"
            name="date_to"
            value="{{ request('date_to') }}"
            class="w-full bg-transparent text-sm font-medium leading-[1.4] text-text-body focus:outline-none"
            placeholder="Date To"
          >
        </div>

        <div class="flex gap-2">
          <button
            type="submit"
            class="fd-btn inline-flex shrink-0 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
          >
            Filter
          </button>
          <a
            href="{{ route('webhooks.logs') }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center rounded border border-solid border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Clear
          </a>
        </div>
      </form>

      {{-- Recent Logs Table --}}
      <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Recent Logs</h2>

        @if ($deliveries->isEmpty())
          <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <p class="text-base font-medium text-text-muted">No delivery logs yet</p>
            <p class="text-sm text-text-subtle opacity-60">Webhook deliveries will appear here once triggered.</p>
          </div>
        @else
          <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[1100px] text-left">
                <thead>
                  <tr class="bg-elevated">
                    <th class="w-[80px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">Event Type</th>
                    <th class="w-[280px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Webhook URL</th>
                    <th class="w-[180px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Payload Data</th>
                    <th class="w-[80px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                    <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Sent At</th>
                    <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Response Time</th>
                    <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($deliveries as $delivery)
                    @php
                      $data = $delivery->payload['data'] ?? [];
                      $statusColor = match ($delivery->status->value) {
                        'sent' => ['rgba(0,128,0,0.1)', 'green', 'Successful'],
                        'failed' => ['rgba(255,0,0,0.1)', 'red', 'Failed'],
                        'pending' => ['rgba(255,165,0,0.1)', 'orange', 'Pending'],
                        'retrying' => ['rgba(255,165,0,0.1)', 'orange', 'Retrying'],
                        default => ['rgba(128,128,128,0.1)', 'gray', $delivery->status->value],
                      };
                    @endphp
                    <tr class="border-t border-divider bg-elevated">
                      <td class="w-[80px] p-2">
                        <span class="inline-flex items-center justify-center rounded bg-stat-blue/15 px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-stat-blue">
                          {{ $delivery->event_type === 'new_lead' ? 'New_Lead' : $delivery->event_type }}
                        </span>
                      </td>
                      <td class="w-[280px] max-w-[280px] truncate p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">
                        {{ $delivery->subscription?->url ?? 'N/A' }}
                      </td>
                      <td class="w-[180px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">
                        @if (! empty($data))
                          <span class="font-bold">Name:</span>&nbsp;{{ $data['name'] ?? 'N/A' }}<br>
                          <span class="font-bold">Phone:</span>&nbsp;{{ $data['phone'] ?? 'N/A' }}<br>
                          <span class="font-bold">Message:</span>&nbsp;{{ Str::limit($data['message'] ?? '', 30) }}
                        @else
                          <span class="text-text-muted">No data</span>
                        @endif
                      </td>
                      <td class="w-[80px] p-2">
                        <span
                          class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap"
                          style="background-color: {{ $statusColor[0] }}; color: {{ $statusColor[1] }};"
                        >
                          {{ $statusColor[2] }}
                        </span>
                      </td>
                      <td class="w-[160px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">
                        {{ $delivery->sent_at ? $delivery->sent_at->format('Y-m-d H:i:s') : '—' }}
                      </td>
                      <td class="p-2 text-center text-[13px] font-normal leading-[1.5] text-text-body">
                        {{ $delivery->duration_ms !== null ? $delivery->duration_ms . 'ms' : '—' }}
                      </td>
                      <td class="p-2">
                        <div class="flex items-center justify-center gap-6">
                          @if ($delivery->status === \App\Enums\WebhookDeliveryStatus::Failed)
                            <form action="{{ route('webhooks.logs.retry', $delivery->id) }}" method="POST">
                              @csrf
                              <button type="submit" aria-label="Retry">
                                <img src="{{ asset('images/webhooks/rotate-right.svg') }}" alt="" class="size-5" width="20" height="20">
                              </button>
                            </form>
                          @endif
                          <a href="{{ route('webhooks.logs.detail', $delivery->id) }}" aria-label="View">
                            <img src="{{ asset('images/webhooks/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                          </a>
                          <form action="{{ route('webhooks.logs.destroy', $delivery->id) }}" method="POST" onsubmit="return confirm('Delete this log?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="Delete">
                              <img src="{{ asset('images/webhooks/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @if ($deliveries->hasPages())
              <x-ui.table-pagination :paginator="$deliveries" />
            @endif
          </div>
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
