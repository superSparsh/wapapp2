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
                <label class="mb-1 block text-sm font-medium text-text-primary">WhatsApp Number (Optional)</label>
                <input type="text" name="settings[whatsapp_number]"
                  value="{{ old('settings.whatsapp_number', $integration->settings['whatsapp_number'] ?? '') }}"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="e.g. +911234567890" />
                @error('settings.whatsapp_number')
                  <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Admin number that receives booking notifications.</p>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-text-primary">Calendly Email (Optional)</label>
                <input type="email" name="settings[user_email]"
                  value="{{ old('settings.user_email', $integration->settings['user_email'] ?? '') }}"
                  class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-green-500"
                  placeholder="e.g. your@email.com" />
                @error('settings.user_email')
                  <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
              </div>

              <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                  <input type="checkbox" name="settings[enable_whatsapp]" id="enable_whatsapp" value="on"
                    {{ old('settings.enable_whatsapp', $integration->settings['enable_whatsapp'] ?? false) ? 'checked' : '' }}
                    class="size-4 rounded border-border text-green-500" />
                  <label for="enable_whatsapp" class="text-sm font-medium text-text-primary">Enable WhatsApp Notifications</label>
                </div>
                <p class="text-xs leading-relaxed text-text-muted pl-7">
                  To send meeting notifications to <strong class="text-text-primary">customers who book</strong>, add a custom question in your Calendly event type
                  (e.g. &quot;WhatsApp number&quot; or &quot;Phone number&quot;) so we can send them the confirmation.
                  Without it, only the admin receives WhatsApp notifications.
                </p>
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
        @php
          $timezone = config('app.timezone', 'UTC');
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex gap-2">
            @foreach (['all' => 'All', 'active' => 'Active', 'canceled' => 'Canceled'] as $s => $label)
              <a href="{{ route('integration.calendly', ['tab' => 'events', 'status' => $s === 'all' ? null : $s]) }}"
                class="rounded-lg border px-3 py-1 text-sm transition-colors {{ request('status', 'all') === $s ? 'border-green-500 bg-green-500 text-white' : 'border-border bg-elevated text-text-muted hover:bg-surface' }}">
                {{ $label }}
              </a>
            @endforeach
          </div>
          <button type="button" id="sync-events-btn"
            class="inline-flex items-center gap-2 rounded border border-border bg-elevated px-4 py-2 text-sm font-medium text-text-primary hover:bg-surface transition-colors disabled:opacity-60">
            <svg id="sync-spinner" class="hidden size-4 animate-spin text-green-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Sync Events
          </button>
        </div>

        @if ($events->isEmpty())
          <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-10 text-center">
            <p class="text-sm text-text-muted">No events found. Sync your Calendly to load events.</p>
          </div>
        @else
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($events as $event)
              @php
                $host = $event->raw_payload['event_memberships'][0] ?? null;
                $hostName = $host['user_name'] ?? null;
                $hostEmail = $host['user_email'] ?? null;
                $locationType = $event->raw_payload['location']['type'] ?? null;
                $totalInvitees = $event->raw_payload['invitees_counter']['total'] ?? null;
                $eventName = $event->raw_payload['name'] ?? $event->event_type ?? 'Meeting';
                $statusValue = $event->status?->value ?? 'unknown';

                $start = $event->start_time?->copy()->timezone($timezone);
                $end = $event->end_time?->copy()->timezone($timezone);
                $formattedStart = $start?->format('d M Y, h:i A') ?? 'N/A';
                $formattedEnd = $end?->format('d M Y, h:i A') ?? 'N/A';
                $durationLabel = 'N/A';
                if ($start && $end) {
                  $durationMinutes = $start->diffInMinutes($end);
                  if ($durationMinutes >= 60) {
                    $hours = intdiv($durationMinutes, 60);
                    $mins = $durationMinutes % 60;
                    $durationLabel = $hours.' hr'.($hours > 1 ? 's' : '');
                    if ($mins) {
                      $durationLabel .= ' '.$mins.' min';
                    }
                  } else {
                    $durationLabel = $durationMinutes.' min';
                  }
                }

                $formResponsesRaw = $event->form_responses ?? [];
                if (is_string($formResponsesRaw)) {
                  $decoded = json_decode($formResponsesRaw, true);
                  $formResponsesRaw = is_array($decoded) ? $decoded : [];
                }
                $formQuestions = $formResponsesRaw['questions_and_answers'] ?? $formResponsesRaw['questions'] ?? [];
                $formDisplayItems = [];
                if (! empty($formResponsesRaw['name'])) {
                  $formDisplayItems[] = ['question' => 'Name', 'answer' => $formResponsesRaw['name']];
                }
                if (! empty($formResponsesRaw['email'])) {
                  $formDisplayItems[] = ['question' => 'Email', 'answer' => $formResponsesRaw['email']];
                }
                if (! empty($formResponsesRaw['timezone'])) {
                  $formDisplayItems[] = ['question' => 'Timezone', 'answer' => $formResponsesRaw['timezone']];
                }
                if (! empty($formResponsesRaw['text_reminder_number'])) {
                  $formDisplayItems[] = ['question' => 'Text reminder number', 'answer' => $formResponsesRaw['text_reminder_number']];
                }
                if (is_array($formQuestions)) {
                  foreach ($formQuestions as $q) {
                    if (! is_array($q)) {
                      continue;
                    }
                    $formDisplayItems[] = [
                      'question' => $q['question'] ?? '',
                      'answer' => $q['answer'] ?? '',
                    ];
                  }
                }
              @endphp
              <article
                role="button"
                tabindex="0"
                data-calendly-event-card
                data-event-name="{{ $eventName }}"
                data-event-status="{{ ucfirst($statusValue) }}"
                data-event-start="{{ $formattedStart }}"
                data-event-end="{{ $formattedEnd }}"
                data-event-invitee="{{ $event->invitee_email ?? 'N/A' }}"
                data-event-host-name="{{ $hostName ?? 'N/A' }}"
                data-event-host-email="{{ $hostEmail ?? 'N/A' }}"
                data-event-location-type="{{ $locationType ?? 'N/A' }}"
                data-event-total-invitees="{{ $totalInvitees ?? 'N/A' }}"
                data-event-whatsapp="{{ $event->whatsapp_number ?? 'N/A' }}"
                data-event-duration="{{ $durationLabel }}"
                data-event-timezone="{{ $timezone }}"
                data-event-form='@json($formDisplayItems)'
                class="flex cursor-pointer flex-col gap-4 rounded-lg border border-border bg-elevated p-4 shadow-sm transition-colors hover:border-green-500/40">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-text-muted">Calendly Event</p>
                    <p class="truncate text-sm font-semibold text-text-primary">{{ $eventName }}</p>
                    <p class="mt-1 text-xs text-text-muted">{{ $formattedStart }}</p>
                  </div>
                  <span @class([
                    'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[10px] font-semibold capitalize',
                    'bg-green-100 text-green-700' => $statusValue === 'active',
                    'bg-red-100 text-red-600' => $statusValue === 'canceled',
                    'bg-yellow-100 text-yellow-700' => $statusValue === 'rescheduled',
                    'bg-gray-100 text-gray-600' => ! in_array($statusValue, ['active', 'canceled', 'rescheduled'], true),
                  ])>{{ $statusValue }}</span>
                </div>
                <div class="mt-auto flex items-center justify-between gap-2 border-t border-border pt-3">
                  <span class="truncate text-xs text-text-muted">
                    {{ $event->whatsapp_number ?? $event->invitee_email ?? 'Contact details' }}
                  </span>
                  <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-green-600">
                    View details
                    <span aria-hidden="true">&rarr;</span>
                  </span>
                </div>
              </article>
            @endforeach
          </div>
          <x-ui.table-pagination :paginator="$events" />
        @endif

        {{-- Event details modal --}}
        <div id="modal-calendly-event-details" data-modal="calendly-event-details"
          class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true"
          aria-labelledby="calendly-event-modal-title">
          <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-elevated shadow-xl">
            <div class="flex items-start justify-between gap-3 bg-gradient-to-br from-[#25D366] to-[#128C7E] px-5 py-4 text-white">
              <div>
                <p class="text-[10px] font-semibold uppercase tracking-wider opacity-85">Event details</p>
                <h2 id="calendly-event-modal-title" class="text-lg font-semibold">Calendly booking overview</h2>
              </div>
              <button type="button" data-modal-close class="flex size-8 items-center justify-center rounded-lg bg-white/15 hover:bg-white/25" aria-label="Close">
                <span class="text-xl leading-none">&times;</span>
              </button>
            </div>
            <div class="overflow-y-auto p-5">
              <div class="grid gap-4 lg:grid-cols-12">
                <div class="flex flex-col gap-4 lg:col-span-7">
                  <div class="rounded-xl border-l-4 border-[#25D366] bg-surface p-4 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-text-muted">Event</p>
                    <h3 id="modalEventName" class="mt-1 text-base font-semibold text-text-primary"></h3>
                    <p id="modalEventStartSummary" class="mt-1 text-sm text-text-muted"></p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                      <span class="rounded-full border border-border bg-elevated px-2.5 py-1 text-text-muted">
                        Duration: <span id="modalEventDuration" class="font-medium text-text-primary"></span>
                      </span>
                      <span class="rounded-full border border-border bg-elevated px-2.5 py-1 text-text-muted">
                        Timezone: <span id="modalEventTimezone" class="font-medium text-text-primary"></span>
                      </span>
                      <span id="modalEventStatus" class="rounded-full bg-[#128C7E] px-2.5 py-1 font-semibold text-white"></span>
                    </div>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                      <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-text-muted">Timing</p>
                      <p class="text-sm text-text-primary"><span class="font-semibold">Start:</span> <span id="modalEventStart"></span></p>
                      <p class="mt-1 text-sm text-text-primary"><span class="font-semibold">End:</span> <span id="modalEventEnd"></span></p>
                    </div>
                    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                      <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-text-muted">Location &amp; capacity</p>
                      <p class="text-sm text-text-primary"><span class="font-semibold">Location type:</span> <span id="modalEventLocationType"></span></p>
                      <p class="mt-1 text-sm text-text-primary"><span class="font-semibold">Total invitees:</span> <span id="modalEventTotalInvitees"></span></p>
                    </div>
                  </div>
                </div>
                <div class="flex flex-col gap-3 lg:col-span-5">
                  <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-text-muted">Invitee</p>
                    <p class="text-sm text-text-primary"><span class="font-semibold">Email:</span> <span id="modalEventInvitee"></span></p>
                  </div>
                  <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-text-muted">Host</p>
                    <p class="text-sm text-text-primary"><span class="font-semibold">Name:</span> <span id="modalEventHostName"></span></p>
                    <p class="mt-1 text-sm text-text-primary"><span class="font-semibold">Email:</span> <span id="modalEventHostEmail"></span></p>
                  </div>
                  <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-text-muted">WhatsApp</p>
                    <p class="text-sm text-text-primary"><span class="font-semibold">Number:</span> <span id="modalEventWhatsapp"></span></p>
                  </div>
                </div>
                <div class="lg:col-span-12">
                  <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
                    <p class="mb-3 text-[10px] font-bold uppercase tracking-wide text-text-muted">Form responses</p>
                    <ul id="modalEventFormList" class="grid gap-2 sm:grid-cols-2"></ul>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex justify-end border-t border-border px-5 py-3">
              <button type="button" data-modal-close class="rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text-primary hover:bg-surface">
                Close
              </button>
            </div>
          </div>
        </div>
      @endif

      {{-- Logs Tab --}}
      @if ($activeTab === 'logs')
        @php
          $logCollection = $messageLogs->getCollection();
          $pageSent = $logCollection->filter(fn ($l) => ($l->status?->value ?? $l->status) === 'sent')->count();
          $pageFailed = $logCollection->filter(fn ($l) => ($l->status?->value ?? $l->status) === 'failed')->count();
          $pageSkipped = $logCollection->filter(fn ($l) => ($l->status?->value ?? $l->status) === 'skipped')->count();
        @endphp

        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-text-primary">WhatsApp Message Logs</h2>
            <p class="text-xs text-text-muted">All messages sent via Calendly integration</p>
          </div>
          <div class="flex flex-wrap gap-2">
            @foreach ([
              'all' => ['label' => 'All', 'active' => 'border-green-500 bg-green-500 text-white'],
              'sent' => ['label' => 'Sent', 'active' => 'border-green-500 bg-green-500 text-white'],
              'failed' => ['label' => 'Failed', 'active' => 'border-red-500 bg-red-500 text-white'],
              'skipped' => ['label' => 'Skipped', 'active' => 'border-yellow-500 bg-yellow-500 text-white'],
            ] as $t => $meta)
              @php $isActive = request('log_type', 'all') === $t; @endphp
              <a href="{{ route('integration.calendly', ['tab' => 'logs', 'log_type' => $t === 'all' ? null : $t]) }}"
                class="rounded-lg border px-3 py-1 text-sm transition-colors {{ $isActive ? $meta['active'] : 'border-border bg-elevated text-text-muted hover:bg-surface' }}">
                {{ $meta['label'] }}
              </a>
            @endforeach
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
          <div class="rounded-xl border border-border bg-elevated p-3 text-center">
            <div class="text-xl font-bold text-text-primary">{{ $messageLogs->total() }}</div>
            <div class="text-xs text-text-muted">Total Messages</div>
          </div>
          <div class="rounded-xl border border-border bg-elevated p-3 text-center">
            <div class="text-xl font-bold text-green-600">{{ $pageSent }}</div>
            <div class="text-xs text-text-muted">Sent (this page)</div>
          </div>
          <div class="rounded-xl border border-border bg-elevated p-3 text-center">
            <div class="text-xl font-bold text-red-600">{{ $pageFailed }}</div>
            <div class="text-xs text-text-muted">Failed (this page)</div>
          </div>
          <div class="rounded-xl border border-border bg-elevated p-3 text-center">
            <div class="text-xl font-bold text-yellow-600">{{ $pageSkipped }}</div>
            <div class="text-xs text-text-muted">Skipped (this page)</div>
          </div>
          <div class="rounded-xl border border-border bg-elevated p-3 text-center">
            <div class="text-xl font-bold text-text-primary">{{ $messageLogs->lastPage() }}</div>
            <div class="text-xs text-text-muted">Total Pages</div>
          </div>
        </div>

        @if ($messageLogs->isEmpty())
          <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-elevated py-10 text-center">
            <p class="text-sm text-text-muted">No message logs yet.</p>
          </div>
        @else
          <div class="flex flex-col gap-3">
            @foreach ($messageLogs as $log)
              @php
                $logStatus = $log->status?->value ?? (string) $log->status;
                $typeColors = [
                  'created' => 'bg-green-100 text-green-700',
                  'canceled' => 'bg-red-100 text-red-600',
                  'rescheduled' => 'bg-yellow-100 text-yellow-700',
                ];
                $typeClass = $typeColors[$log->event_type] ?? 'bg-gray-100 text-gray-700';
              @endphp
              <div class="flex flex-wrap items-start gap-3 rounded-xl border border-border bg-elevated p-4 shadow-sm">
                <div class="min-w-0 flex-1">
                  <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] font-semibold capitalize text-gray-700">
                      {{ $log->recipient_type ?? '—' }}
                    </span>
                    <span @class(['rounded px-2 py-0.5 text-[10px] font-semibold capitalize', $typeClass])>
                      {{ $log->event_type ?? '—' }}
                    </span>
                    @if ($logStatus === 'sent')
                      <span class="rounded bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">Sent</span>
                    @elseif ($logStatus === 'skipped')
                      <span class="rounded bg-yellow-100 px-2 py-0.5 text-[10px] font-semibold text-yellow-800">Skipped</span>
                    @else
                      <span class="rounded bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-600">Failed</span>
                    @endif
                  </div>
                  <div class="text-xs text-text-muted">
                    <span class="font-medium text-text-primary">Event:</span> {{ $log->event_name ?? 'N/A' }}
                    @if ($log->recipient_number)
                      <span class="mx-1">|</span>
                      <span class="font-medium text-text-primary">Number:</span>
                      {{ str_starts_with((string) $log->recipient_number, '+') ? $log->recipient_number : '+'.$log->recipient_number }}
                    @endif
                    @if ($log->invitee_email)
                      <span class="mx-1">|</span>
                      <span class="font-medium text-text-primary">Email:</span> {{ $log->invitee_email }}
                    @endif
                  </div>
                  @if (in_array($logStatus, ['failed', 'skipped'], true) && $log->error_message)
                    <p class="mt-2 text-xs text-red-600">
                      <span class="font-semibold">Error:</span> {{ $log->error_message }}
                    </p>
                  @endif
                </div>
                <div class="shrink-0 text-right text-xs text-text-muted whitespace-nowrap">
                  {{ $log->sent_at?->format('d M Y') ?? '—' }}
                  <br>
                  {{ $log->sent_at?->format('h:i A') ?? '' }}
                </div>
              </div>
            @endforeach
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

    document.getElementById('sync-events-btn')?.addEventListener('click', function () {
      const btn = this;
      const spinner = document.getElementById('sync-spinner');
      btn.disabled = true;
      spinner?.classList.remove('hidden');

      fetch('{{ route('integration.calendly.sync') }}', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      })
        .then(res => res.json())
        .then(data => {
          spinner?.classList.add('hidden');
          btn.disabled = false;
          if (data.success) {
            window.showSuccessToast?.(data.message || 'Sync started in background.');
            setTimeout(() => location.reload(), 5000);
          } else {
            window.showErrorToast?.(data.message || 'Sync failed.');
          }
        })
        .catch(() => {
          spinner?.classList.add('hidden');
          btn.disabled = false;
          window.showWarningToast?.('Sync failed.');
        });
    });

    (function initCalendlyEventModal() {
      const modal = document.getElementById('modal-calendly-event-details');
      if (!modal) return;

      const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
      };

      const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
      };

      const fillModal = (trigger) => {
        const get = (name) => trigger.getAttribute('data-' + name) || 'N/A';
        const setText = (id, value) => {
          const el = document.getElementById(id);
          if (el) el.textContent = value;
        };

        setText('modalEventName', get('event-name'));
        setText('modalEventStartSummary', get('event-start'));
        setText('modalEventStart', get('event-start'));
        setText('modalEventEnd', get('event-end'));
        setText('modalEventDuration', get('event-duration'));
        setText('modalEventTimezone', get('event-timezone'));
        setText('modalEventInvitee', get('event-invitee'));
        setText('modalEventHostName', get('event-host-name'));
        setText('modalEventHostEmail', get('event-host-email'));
        setText('modalEventLocationType', get('event-location-type'));
        setText('modalEventTotalInvitees', get('event-total-invitees'));
        setText('modalEventWhatsapp', get('event-whatsapp'));

        const statusText = get('event-status');
        const statusEl = document.getElementById('modalEventStatus');
        if (statusEl) {
          statusEl.textContent = statusText;
          statusEl.style.backgroundColor = statusText.toLowerCase() === 'active' ? '#128C7E' : '#dc3545';
        }

        const formContainer = document.getElementById('modalEventFormList');
        if (!formContainer) return;

        let items = [];
        try {
          const parsed = JSON.parse(trigger.getAttribute('data-event-form') || '[]');
          if (Array.isArray(parsed)) items = parsed;
        } catch (e) {
          items = [];
        }

        if (!items.length) {
          formContainer.innerHTML = '<li class="text-sm text-text-muted">No additional form responses.</li>';
          return;
        }

        formContainer.innerHTML = items
          .filter(item => item && item.answer)
          .map(item => {
            const question = escapeHtml(String(item.question || '').trim());
            const answer = escapeHtml(String(item.answer || '').trim());
            if (!answer) return '';
            return (
              '<li class="rounded-lg border border-border bg-elevated px-3 py-2">' +
              '<div class="text-[10px] font-bold uppercase tracking-wide text-text-muted">' + question + '</div>' +
              '<div class="mt-1 text-sm font-medium text-text-primary">' + answer + '</div>' +
              '</li>'
            );
          })
          .join('') || '<li class="text-sm text-text-muted">No additional form responses.</li>';
      };

      document.querySelectorAll('[data-calendly-event-card]').forEach((card) => {
        const open = () => {
          fillModal(card);
          openModal();
        };
        card.addEventListener('click', open);
        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open();
          }
        });
      });
    })();
  </script>
</x-layouts.app>
