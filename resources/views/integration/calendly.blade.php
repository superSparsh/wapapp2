<x-layouts.app title="Calendly - WapApp" active="integration.calendly" headerTitle="Calendly">
  <div class="flex flex-col">
    <div class="flex items-center justify-between gap-4 p-4">
      <x-ui.page-header title="Calendly" />
      <form method="POST" action="{{ route('integration.calendly.toggle') }}">
        @csrf
        <button type="submit" class="fd-btn inline-flex shrink-0 items-center justify-center rounded border px-4 py-2 text-sm font-semibold transition-colors
          {{ $integration->isEnabled() ? 'border-red-500 text-red-500 hover:bg-red-50' : 'border-green-500 text-green-500 hover:bg-green-50' }}">
          {{ $integration->isEnabled() ? 'Uninstall Plugin' : 'Enable Plugin' }}
        </button>
      </form>
    </div>

    @if (session('success'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="mx-4 mb-2 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      {{-- Tabs --}}
      <div class="flex flex-wrap gap-3">
        @foreach ([
          ['id' => 'token', 'label' => 'Access Token'],
          ['id' => 'notifications', 'label' => 'Notifications'],
          ['id' => 'events', 'label' => 'Your Events'],
          ['id' => 'logs', 'label' => 'Message Logs'],
        ] as $tab)
          <a href="{{ route('integration.calendly', ['tab' => $tab['id']]) }}"
            @class([
              'fd-tab inline-flex items-center justify-center rounded-lg border border-border-sidebar px-5 py-2 text-sm font-medium leading-[1.4] shadow-sm transition-colors',
              'bg-green-500 text-primary-2' => $activeTab === $tab['id'],
              'bg-elevated text-text-primary opacity-50 hover:opacity-100' => $activeTab !== $tab['id'],
            ])>
            {{ $tab['label'] }}
          </a>
        @endforeach
      </div>

      {{-- Access Token Tab --}}
      @if ($activeTab === 'token')
        <div class="rounded-xl border border-border bg-elevated p-6">
          <h2 class="mb-4 text-base font-semibold text-text-primary">Connect Calendly</h2>
          <form method="POST" action="{{ route('integration.calendly.update') }}">
            @csrf @method('PUT')
            <div class="flex flex-col gap-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">Access Token <span class="text-red-500">*</span></label>
                <input type="text" name="settings[access_token]"
                  value="{{ old('settings.access_token', $integration->settings['access_token'] ?? '') }}"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="eyJhbGci..." />
                @error('settings.access_token')
                  <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
              </div>

              <div class="flex items-center gap-2">
                <button type="button" id="test-token-btn"
                  class="rounded border border-border bg-elevated px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface transition-colors">
                  Test Token
                </button>
                <span id="test-token-result" class="hidden text-sm"></span>
              </div>

              <div class="flex justify-end">
                <button type="submit" class="fd-btn rounded bg-green-500 px-6 py-2 text-sm font-semibold text-primary-2 hover:opacity-90">
                  Save & Connect
                </button>
              </div>
            </div>
          </form>
        </div>
      @endif

      {{-- Notifications Tab --}}
      @if ($activeTab === 'notifications')
        <div class="rounded-xl border border-border bg-elevated p-6">
          <h2 class="mb-4 text-base font-semibold text-text-primary">WhatsApp Notifications</h2>
          <form method="POST" action="{{ route('integration.calendly.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="settings[access_token]" value="{{ $integration->settings['access_token'] ?? '' }}">
            <div class="flex flex-col gap-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">WhatsApp Number (for admin notifications)</label>
                <input type="text" name="settings[whatsapp_number]"
                  value="{{ old('settings.whatsapp_number', $integration->settings['whatsapp_number'] ?? '') }}"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="+911234567890" />
                @error('settings.whatsapp_number')
                  <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
              </div>

              <div class="flex items-center gap-3">
                <input type="checkbox" name="settings[enable_whatsapp]" id="enable_whatsapp" value="on"
                  {{ ($integration->settings['enable_whatsapp'] ?? false) ? 'checked' : '' }}
                  class="size-4 rounded border-border text-green-500" />
                <label for="enable_whatsapp" class="text-sm font-medium text-text-primary">Enable WhatsApp notifications</label>
              </div>

              <div class="flex justify-end">
                <button type="submit" class="fd-btn rounded bg-green-500 px-6 py-2 text-sm font-semibold text-primary-2 hover:opacity-90">
                  Save Settings
                </button>
              </div>
            </div>
          </form>
        </div>
      @endif

      {{-- Events Tab --}}
      @if ($activeTab === 'events')
        <div class="flex items-center justify-between">
          <div class="flex gap-2">
            @foreach (['all' => 'All', 'active' => 'Active', 'canceled' => 'Canceled'] as $s => $label)
              <a href="{{ route('integration.calendly', ['tab' => 'events', 'status' => $s === 'all' ? null : $s]) }}"
                class="rounded-lg border px-3 py-1 text-sm transition-colors {{ request('status', 'all') === $s ? 'border-green-500 bg-green-500 text-white' : 'border-border bg-elevated text-text-muted hover:bg-surface' }}">
                {{ $label }}
              </a>
            @endforeach
          </div>
          <form method="POST" action="{{ route('integration.calendly.sync') }}">
            @csrf
            <button type="submit" class="rounded border border-border bg-elevated px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface transition-colors">
              Sync Events
            </button>
          </form>
        </div>

        @if ($events->isEmpty())
          <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-10 text-center">
            <p class="text-sm text-text-muted">No events found. Sync your Calendly to load events.</p>
          </div>
        @else
          <div class="flex flex-col gap-2">
            @foreach ($events as $event)
              <div class="flex items-center justify-between rounded-xl border border-border bg-elevated p-4">
                <div class="flex flex-col gap-1">
                  <p class="text-sm font-semibold text-text-primary">{{ $event->event_type }}</p>
                  <p class="text-xs text-text-muted">{{ $event->invitee_email }}</p>
                  <p class="text-xs text-text-muted">{{ $event->start_time?->format('M d, Y H:i') }}</p>
                </div>
                <span @class([
                  'inline-flex items-center rounded px-2 py-1 text-[10px] font-medium',
                  'bg-green-100 text-green-700' => $event->status?->value === 'active',
                  'bg-red-100 text-red-600' => $event->status?->value === 'canceled',
                  'bg-yellow-100 text-yellow-700' => $event->status?->value === 'rescheduled',
                ])>{{ ucfirst($event->status?->value ?? 'unknown') }}</span>
              </div>
            @endforeach
          </div>
          <x-ui.table-pagination :paginator="$events" />
        @endif
      @endif

      {{-- Logs Tab --}}
      @if ($activeTab === 'logs')
        <div class="flex gap-2">
          @foreach (['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed'] as $t => $label)
            <a href="{{ route('integration.calendly', ['tab' => 'logs', 'log_type' => $t === 'all' ? null : $t]) }}"
              class="rounded-lg border px-3 py-1 text-sm transition-colors {{ request('log_type', 'all') === $t ? 'border-green-500 bg-green-500 text-white' : 'border-border bg-elevated text-text-muted hover:bg-surface' }}">
              {{ $label }}
            </a>
          @endforeach
        </div>

        @if ($messageLogs->isEmpty())
          <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-10 text-center">
            <p class="text-sm text-text-muted">No message logs yet.</p>
          </div>
        @else
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border text-left text-xs font-semibold text-text-muted">
                  <th class="p-2">Recipient</th>
                  <th class="p-2">Event</th>
                  <th class="p-2">Status</th>
                  <th class="p-2">Sent At</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($messageLogs as $log)
                  <tr class="border-b border-border bg-elevated">
                    <td class="p-2">{{ $log->recipient_number }}</td>
                    <td class="p-2">{{ $log->event_name }}</td>
                    <td class="p-2">
                      <span @class(['inline-flex rounded px-2 py-1 text-[10px] font-medium', 'bg-green-100 text-green-700' => $log->status?->value === 'sent', 'bg-red-100 text-red-600' => $log->status?->value === 'failed'])>
                        {{ ucfirst($log->status?->value ?? '') }}
                      </span>
                    </td>
                    <td class="p-2 whitespace-nowrap">{{ $log->sent_at?->format('M d, Y H:i') ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <x-ui.table-pagination :paginator="$messageLogs" />
        @endif
      @endif
    </section>
  </div>

  <script>
    document.getElementById('test-token-btn')?.addEventListener('click', function () {
      const token = document.querySelector('[name="settings[access_token]"]')?.value;
      const result = document.getElementById('test-token-result');
      if (!token) return;
      result.className = 'text-sm text-text-muted';
      result.textContent = 'Testing...';
      result.classList.remove('hidden');
      fetch('{{ route('integration.calendly.test') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ access_token: token }),
      }).then(r => r.json()).then(data => {
        result.textContent = data.message;
        result.className = 'text-sm ' + (data.success ? 'text-green-600' : 'text-red-600');
      }).catch(() => {
        result.textContent = 'Request failed.';
        result.className = 'text-sm text-red-600';
      });
    });
  </script>
</x-layouts.app>
