<x-layouts.app title="Google Calendar - WapApp" active="integration.google-calendar" headerTitle="Google Calendar">
  <div class="flex flex-col">
    <div class="flex items-center justify-between gap-4 p-4">
      <x-ui.page-header title="Google Calendar" />
      <form method="POST" action="{{ route('integration.google-calendar.toggle') }}">
        @csrf
        <button type="submit" class="fd-btn inline-flex shrink-0 items-center justify-center rounded border px-4 py-2 text-sm font-semibold transition-colors
          {{ $integration->isEnabled() ? 'border-red-500 text-red-500 hover:bg-red-50' : 'border-green-500 text-green-500 hover:bg-green-50' }}">
          {{ $integration->isEnabled() ? 'Disable' : 'Enable' }}
        </button>
      </form>
    </div>

    @if (session('success'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="mx-4 mb-2 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="mx-4 mb-2 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-700">
        @foreach ($errors->all() as $err)<p>{{ $err }}</p>@endforeach
      </div>
    @endif

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      {{-- Tabs --}}
      <div class="flex flex-wrap gap-3">
        @foreach ([
          ['id' => 'connect',       'label' => 'Connect'],
          ['id' => 'notifications', 'label' => 'Notifications'],
          ['id' => 'events',        'label' => 'Events'],
          ['id' => 'create',        'label' => 'Create Meeting'],
          ['id' => 'booking',       'label' => 'Booking Links'],
          ['id' => 'logs',          'label' => 'Message Logs'],
        ] as $tab)
          <a href="{{ route('integration.google-calendar', ['tab' => $tab['id']]) }}"
            @class([
              'fd-tab inline-flex items-center justify-center rounded-lg border border-border-sidebar px-4 py-2 text-sm font-medium leading-[1.4] shadow-sm transition-colors',
              'bg-green-500 text-primary-2' => $activeTab === $tab['id'],
              'bg-elevated text-text-primary opacity-50 hover:opacity-100' => $activeTab !== $tab['id'],
            ])>
            {{ $tab['label'] }}
          </a>
        @endforeach
      </div>

      {{-- Connect Tab --}}
      @if ($activeTab === 'connect')
        <div class="rounded-xl border border-border bg-elevated p-6">
          @if ($connected)
            <div class="mb-4 flex items-center gap-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
              <span class="font-semibold">Connected</span>
              <span class="text-text-muted">{{ $integration->settings['user_email'] ?? '' }} ({{ $integration->settings['google_account_name'] ?? '' }})</span>
            </div>
            <div class="flex gap-3">
              <a href="{{ route('integration.google-calendar.oauth') }}"
                class="rounded bg-green-500 px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Re-authorize</a>
              <form method="POST" action="{{ route('integration.google-calendar.disconnect') }}">
                @csrf
                <button type="submit" class="rounded border border-red-500 px-5 py-2 text-sm font-semibold text-red-500 hover:bg-red-50">Disconnect</button>
              </form>
            </div>
          @else
            <p class="mb-4 text-sm text-text-muted">Connect your Google account to sync Google Calendar events and create Google Meet links.</p>
            @if ($oauthConfigured)
              <a href="{{ route('integration.google-calendar.oauth') }}"
                class="inline-flex items-center gap-2 rounded bg-green-500 px-5 py-2 text-sm font-semibold text-white hover:opacity-90">
                Connect Google Account
              </a>
            @else
              <div class="rounded-lg bg-yellow-50 px-4 py-3 text-sm text-yellow-700">
                Google OAuth is not configured. Set <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in your <code>.env</code>.
              </div>
            @endif
          @endif
        </div>
      @endif

      {{-- Notifications Tab --}}
      @if ($activeTab === 'notifications')
        <div class="rounded-xl border border-border bg-elevated p-6">
          <h2 class="mb-4 text-base font-semibold text-text-primary">Notification Settings</h2>
          <form method="POST" action="{{ route('integration.google-calendar.update') }}">
            @csrf @method('PUT')
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">WhatsApp Number (admin)</label>
                <input type="text" name="settings[whatsapp_number]"
                  value="{{ old('settings.whatsapp_number', $integration->settings['whatsapp_number'] ?? '') }}"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="+911234567890" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">Calendar</label>
                <select name="settings[calendar_id]"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                  <option value="primary" {{ ($integration->settings['calendar_id'] ?? 'primary') === 'primary' ? 'selected' : '' }}>Primary</option>
                  @foreach ($calendars as $cal)
                    <option value="{{ $cal['id'] }}" {{ ($integration->settings['calendar_id'] ?? '') === $cal['id'] ? 'selected' : '' }}>
                      {{ $cal['summary'] ?? $cal['id'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">Reminder (minutes before)</label>
                <select name="settings[reminder_minutes_before]"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                  @foreach ($reminderOptions as $mins)
                    <option value="{{ $mins }}" {{ ($integration->settings['reminder_minutes_before'] ?? 30) == $mins ? 'selected' : '' }}>
                      {{ $mins >= 60 ? ($mins / 60).'h' : $mins.'min' }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="flex flex-col gap-3 pt-5">
                <label class="flex items-center gap-2 text-sm font-medium text-text-primary">
                  <input type="checkbox" name="settings[enable_whatsapp]" value="on"
                    {{ ($integration->settings['enable_whatsapp'] ?? false) ? 'checked' : '' }}
                    class="size-4 rounded border-border text-green-500" />
                  Enable WhatsApp Notifications
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-text-primary">
                  <input type="checkbox" name="settings[meet_only]" value="on"
                    {{ ($integration->settings['meet_only'] ?? false) ? 'checked' : '' }}
                    class="size-4 rounded border-border text-green-500" />
                  Google Meet Only (ignore non-Meet events)
                </label>
              </div>
            </div>
            <div class="mt-4 flex justify-end">
              <button type="submit" class="fd-btn rounded bg-green-500 px-6 py-2 text-sm font-semibold text-primary-2 hover:opacity-90">Save Settings</button>
            </div>
          </form>
        </div>
      @endif

      {{-- Events Tab --}}
      @if ($activeTab === 'events')
        <div class="flex items-center justify-between">
          <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'canceled' => 'Canceled'] as $s => $label)
              <a href="{{ route('integration.google-calendar', ['tab' => 'events', 'status' => $s === 'all' ? null : $s]) }}"
                class="rounded-lg border px-3 py-1 text-sm transition-colors {{ request('status', 'all') === $s ? 'border-green-500 bg-green-500 text-white' : 'border-border bg-elevated text-text-muted hover:bg-surface' }}">
                {{ $label }}
              </a>
            @endforeach
          </div>
          <form method="POST" action="{{ route('integration.google-calendar.sync') }}">
            @csrf
            <button type="submit" class="rounded border border-border bg-elevated px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface transition-colors">Sync</button>
          </form>
        </div>

        @if ($events->isEmpty())
          <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-10 text-center">
            <p class="text-sm text-text-muted">No events found. Connect your Google Calendar and sync.</p>
          </div>
        @else
          <div class="flex flex-col gap-2">
            @foreach ($events as $event)
              <div class="flex items-center justify-between rounded-xl border border-border bg-elevated p-4">
                <div class="flex flex-col gap-1">
                  <p class="text-sm font-semibold text-text-primary">{{ $event->summary }}</p>
                  <p class="text-xs text-text-muted">{{ $event->invitee_email }}</p>
                  <p class="text-xs text-text-muted">{{ $event->start_time?->format('M d, Y H:i') }}</p>
                  @if ($event->meet_link)
                    <a href="{{ $event->meet_link }}" target="_blank" class="text-xs text-green-500 hover:underline">Join Meet</a>
                  @endif
                </div>
                <span @class(['inline-flex rounded px-2 py-1 text-[10px] font-medium', 'bg-green-100 text-green-700' => $event->isActive(), 'bg-red-100 text-red-600' => $event->status?->value === 'canceled', 'bg-yellow-100 text-yellow-700' => $event->isRescheduled()])>
                  {{ ucfirst($event->status?->value ?? 'unknown') }}
                </span>
              </div>
            @endforeach
          </div>
          <x-ui.table-pagination :paginator="$events" />
        @endif
      @endif

      {{-- Create Meeting Tab --}}
      @if ($activeTab === 'create')
        <div class="rounded-xl border border-border bg-elevated p-6">
          <h2 class="mb-4 text-base font-semibold text-text-primary">Create Google Meet Meeting</h2>
          @if (!$connected)
            <p class="text-sm text-text-muted">Connect your Google Calendar first.</p>
          @else
            <form method="POST" action="{{ route('integration.google-calendar.create-meeting') }}">
              @csrf
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Meeting Title <span class="text-red-500">*</span></label>
                  <input type="text" name="summary" value="{{ old('summary') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Start At <span class="text-red-500">*</span></label>
                  <input type="datetime-local" name="start_at" value="{{ old('start_at') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Duration (minutes) <span class="text-red-500">*</span></label>
                  <select name="duration_minutes"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    @foreach ([15, 30, 45, 60, 90, 120] as $d)
                      <option value="{{ $d }}" {{ old('duration_minutes', 30) == $d ? 'selected' : '' }}>{{ $d }} min</option>
                    @endforeach
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Attendee Name <span class="text-red-500">*</span></label>
                  <input type="text" name="attendee_name" value="{{ old('attendee_name') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Attendee Phone (WhatsApp) <span class="text-red-500">*</span></label>
                  <input type="text" name="attendee_phone" value="{{ old('attendee_phone') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="+911234567890" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Attendee Email</label>
                  <input type="email" name="attendee_email" value="{{ old('attendee_email') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                </div>
                <div class="md:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-text-primary">Description</label>
                  <textarea name="description" rows="3"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">{{ old('description') }}</textarea>
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button type="submit" class="fd-btn rounded bg-green-500 px-6 py-2 text-sm font-semibold text-primary-2 hover:opacity-90">Create Meeting</button>
              </div>
            </form>
          @endif
        </div>
      @endif

      {{-- Booking Links Tab --}}
      @if ($activeTab === 'booking')
        <div class="grid gap-6 lg:grid-cols-2">
          <div class="rounded-xl border border-border bg-elevated p-6">
            <h2 class="mb-4 text-base font-semibold text-text-primary">Create Booking Link</h2>
            <form method="POST" action="{{ route('integration.google-calendar.booking.store') }}">
              @csrf
              <div class="flex flex-col gap-3">
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Title <span class="text-red-500">*</span></label>
                  <input type="text" name="title" value="{{ old('title') }}"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                </div>
                <div>
                  <label class="mb-1 block text-sm font-medium text-text-primary">Duration</label>
                  <select name="duration_minutes"
                    class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    @foreach ([15, 30, 45, 60] as $d)
                      <option value="{{ $d }}" {{ old('duration_minutes', 30) == $d ? 'selected' : '' }}>{{ $d }} min</option>
                    @endforeach
                  </select>
                </div>
                <div class="flex justify-end">
                  <button type="submit" class="fd-btn rounded bg-green-500 px-5 py-2 text-sm font-semibold text-primary-2 hover:opacity-90">Create Link</button>
                </div>
              </div>
            </form>
          </div>

          <div class="flex flex-col gap-3">
            <h2 class="text-base font-semibold text-text-primary">Your Booking Links</h2>
            @forelse ($bookingLinks as $link)
              <div class="flex items-center justify-between rounded-xl border border-border bg-elevated p-4">
                <div class="flex flex-col gap-1">
                  <p class="text-sm font-semibold text-text-primary">{{ $link->title }}</p>
                  <p class="text-xs text-text-muted">{{ $link->duration_minutes }} min</p>
                  <a href="{{ $link->public_url }}" target="_blank" class="text-xs text-green-500 hover:underline">{{ $link->public_url }}</a>
                </div>
                <form method="POST" action="{{ route('integration.google-calendar.booking.delete', $link->id) }}">
                  @csrf @method('DELETE')
                  <button type="submit" class="rounded border border-red-300 px-3 py-1 text-xs text-red-500 hover:bg-red-50">Remove</button>
                </form>
              </div>
            @empty
              <div class="rounded-xl border border-border bg-elevated py-8 text-center text-sm text-text-muted">No booking links yet.</div>
            @endforelse
          </div>
        </div>
      @endif

      {{-- Logs Tab --}}
      @if ($activeTab === 'logs')
        <div class="flex gap-2">
          @foreach (['all' => 'All', 'sent' => 'Sent', 'failed' => 'Failed'] as $t => $label)
            <a href="{{ route('integration.google-calendar', ['tab' => 'logs', 'log_type' => $t === 'all' ? null : $t]) }}"
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
</x-layouts.app>
