<x-layouts.app title="Webhook Log Details - WapApp" active="webhooks.logs">
  <div class="flex flex-col bg-surface">
    @php
      $data = $delivery->payload['data'] ?? [];
      $statusLabel = match ($delivery->status->value) {
        'sent' => 'Successful',
        'failed' => 'Failed',
        'pending' => 'Pending',
        'retrying' => 'Retrying',
        default => ucfirst($delivery->status->value),
      };
      $statusBadgeClass = match ($delivery->status->value) {
        'sent' => 'bg-[rgba(0,128,0,0.1)] text-[green]',
        'failed' => 'bg-[rgba(255,0,0,0.1)] text-[red]',
        'pending', 'retrying' => 'bg-[rgba(255,165,0,0.1)] text-[orange]',
        default => 'bg-gray-100 text-gray-600',
      };
    @endphp

    {{-- Flash message --}}
    @if (session('status'))
      <div class="mx-4 mt-3 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-1 p-4">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Webhook Log Details</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Detailed information about this webhook delivery
      </p>
    </div>

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      {{-- Log Information --}}
      <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Log Information</h2>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
          {{-- Left Column --}}
          <div class="min-w-0 flex-1 overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            @php
              $leftRows = [
                ['Event Type:', 'badge-event', $delivery->event_type === 'new_lead' ? 'New_Lead' : $delivery->event_type],
                ['Status:', 'badge-status', $statusLabel],
                ['Sent At:', 'text', $delivery->sent_at?->format('Y-m-d H:i:s') ?? '—'],
                ['Response Received:', 'text', $delivery->response_received_at?->format('Y-m-d H:i:s') ?? '—'],
              ];
            @endphp
            @foreach ($leftRows as $index => [$label, $type, $value])
              <div @class([
                'flex items-center gap-2 px-2',
                'p-2' => $index === 0,
                'px-2 py-1.5' => $index > 0,
                'border-t border-divider' => $index > 0,
              ])>
                <div class="w-[200px] shrink-0 p-2">
                  <p @class([
                    'text-[13px] leading-[1.5]',
                    'font-medium text-text-body' => $index === 0,
                    'font-semibold text-text-subtle' => $index > 0,
                  ])>{{ $label }}</p>
                </div>
                <div class="min-w-0 flex-1 p-2">
                  @if ($type === 'badge-event')
                    <span class="inline-flex items-center justify-center rounded bg-stat-blue/15 px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-stat-blue">
                      {{ $value }}
                    </span>
                  @elseif ($type === 'badge-status')
                    <span class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap {{ $statusBadgeClass }}">
                      {{ $value }}
                    </span>
                  @else
                    <p class="text-[13px] font-medium leading-[1.5] text-text-muted">{{ $value }}</p>
                  @endif
                </div>
              </div>
            @endforeach
          </div>

          {{-- Right Column --}}
          <div class="min-w-0 flex-1 overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            @php
              $rightRows = [
                ['Webhook URL:', 'url', $delivery->subscription?->url ?? 'N/A'],
                ['Error Message:', 'text', $delivery->error_message ?? 'No error'],
                ['HTTP Status:', 'text', $delivery->response_status !== null ? (string) $delivery->response_status : '—'],
                ['Response Time:', 'text', $delivery->duration_ms !== null ? $delivery->duration_ms . 'ms' : '—'],
                ['Attempt Count:', 'text', (string) $delivery->attempt_count],
              ];
            @endphp
            @foreach ($rightRows as $index => [$label, $type, $value])
              <div @class([
                'flex items-center gap-2 px-2',
                'p-2' => $index === 0,
                'px-2 py-1.5' => $index > 0,
                'border-t border-divider' => $index > 0,
              ])>
                <div class="w-[180px] shrink-0 p-2">
                  <p @class([
                    'text-[13px] leading-[1.5]',
                    'font-medium text-text-body' => $index === 0,
                    'font-semibold text-text-subtle' => $index > 0,
                  ])>{{ $label }}</p>
                </div>
                <div class="min-w-0 flex-1 p-2">
                  @if ($type === 'url')
                    <p class="truncate text-[13px] font-medium leading-[1.5] whitespace-pre text-text-muted" title="{{ $value }}">{{ $value }}</p>
                  @else
                    <p class="text-[13px] font-medium leading-[1.5] text-text-muted">{{ $value }}</p>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      {{-- Request Payload --}}
      <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Request Payload</h2>

        <div class="flex gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/webhooks/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
          <div class="flex min-w-0 flex-1 flex-col gap-2.5">
            @if (! empty($data))
              <p class="text-sm font-bold leading-[1.4] text-text-body">Key Information:</p>
              <div class="text-sm font-normal leading-[1.6] text-[#363636]">
                <p>
                  @if (isset($data['name'])) <span class="font-bold">Name:</span>&nbsp;{{ $data['name'] }}<br> @endif
                  @if (isset($data['phone'])) <span class="font-bold">Phone:</span>&nbsp;{{ $data['phone'] }}<br> @endif
                  @if (isset($data['message'])) <span class="font-bold">Message:</span>&nbsp;{{ $data['message'] }}<br> @endif
                  @if (isset($data['id'])) <span class="font-bold">Lead ID:</span>&nbsp;{{ $data['id'] }}<br> @endif
                  @if (isset($data['created_at'])) <span class="font-bold">Created At:</span>&nbsp;{{ $data['created_at'] }} @endif
                </p>
              </div>
            @endif

            <div class="flex flex-col gap-2">
              <p class="text-sm font-semibold leading-[1.4] text-text-primary">Raw JSON Payload:</p>
              <div class="rounded-xl border border-solid border-border bg-elevated p-3.5">
                <pre class="whitespace-pre-wrap text-sm font-medium leading-[1.4] text-text-muted">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
              </div>
            </div>

            <div class="flex flex-col gap-2">
              <p class="text-sm font-semibold leading-[1.4] text-text-primary">Response Body</p>
              <div class="rounded-xl border border-solid border-border bg-elevated p-3.5">
                <p class="text-sm font-medium leading-[1.4] break-all text-text-muted">{{ $delivery->response_body ?? 'No response received' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Actions --}}
      <div class="flex flex-wrap items-center justify-end gap-2.5">
        <a
          href="{{ route('webhooks.logs') }}"
          class="fd-btn inline-flex items-center justify-center rounded border border-solid border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
        >
          Back to Logs
        </a>
        @if ($delivery->status === \App\Enums\WebhookDeliveryStatus::Failed)
          <form action="{{ route('webhooks.logs.retry', $delivery->id) }}" method="POST">
            @csrf
            <button
              type="submit"
              class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
            >
              Retry
            </button>
          </form>
        @endif
        <form action="{{ route('webhooks.logs.destroy', $delivery->id) }}" method="POST" onsubmit="return confirm('Delete this log?')">
          @csrf
          @method('DELETE')
          <button
            type="submit"
            class="fd-btn-sm inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-6 py-3 text-xs font-semibold leading-[1.5] text-[red] transition-opacity hover:opacity-90"
          >
            Delete
          </button>
        </form>
      </div>
    </section>
  </div>
</x-layouts.app>
