@php
  $menuOpen = $menuOpen ?? false;
  $contact = $contact ?? [
    'initials' => '??',
    'name' => 'Unknown',
    'phone' => '',
    'ai_enabled' => false,
  ];
  $messages = $messages ?? [];
  $conversation = $conversation ?? null;
  $assignableAgents = $assignableAgents ?? collect();
  $walletBlocked = (bool) ($walletBlocked ?? false);
  $messagesHasMore = (bool) ($messagesHasMore ?? false);
  $messagesOldestId = $messagesOldestId ?? null;
  $activeLine = $activeLine ?? null;
@endphp

<div
  class="relative flex h-full min-h-0 min-w-0 flex-1 flex-col rounded-xl"
  data-inbox-chat
  data-wallet-blocked="{{ $walletBlocked ? '1' : '0' }}"
  @if ($conversation)
    data-conversation-uuid="{{ $conversation->uuid }}"
    data-messages-url="{{ route('inbox.api.messages', $conversation) }}"
    data-send-url="{{ route('inbox.api.send', $conversation) }}"
    data-media-url="{{ route('inbox.api.send-media', $conversation) }}"
    data-template-url="{{ route('inbox.api.send-template', $conversation) }}"
    data-flow-url="{{ route('inbox.api.send-flow', $conversation) }}"
    data-flows-url="{{ route('getflowData') }}"
    data-interactive-url="{{ route('inbox.api.send-interactive', $conversation) }}"
    data-interactive-messages-url="{{ route('inbox.api.interactive-messages') }}"
    data-location-url="{{ route('inbox.api.send-location', $conversation) }}"
    data-sticker-url="{{ route('inbox.api.send-sticker', $conversation) }}"
    data-contact-url="{{ route('inbox.api.send-contact', $conversation) }}"
    data-payment-url="{{ route('inbox.api.request-payment', $conversation) }}"
    data-opt-in-url="{{ route('inbox.api.resend-opt-in', $conversation) }}"
    data-templates-url="{{ route('inbox.api.templates', array_filter(['line' => $activeLine->uuid ?? null])) }}"
    data-window-url="{{ route('inbox.api.window', $conversation) }}"
    data-window-hours="{{ config('whatsapp.service_window_hours', 24) }}"
    data-read-url="{{ route('inbox.api.read', $conversation) }}"
    data-assign-url="{{ route('inbox.api.assign', $conversation) }}"
    data-export-url="{{ route('inbox.api.export', $conversation) }}"
    data-response-type-url="{{ route('inbox.api.response-type', $conversation) }}"
    data-interactive-compose-url="{{ route('inbox.api.send-interactive-compose', $conversation) }}"
    data-delete-url="{{ route('inbox.api.destroy', $conversation) }}"
  @endif
>
  <img
    src="{{ asset('images/inbox/chat-wallpaper.png') }}"
    alt=""
    class="pointer-events-none absolute inset-0 size-full rounded-xl object-cover"
  >

  <div class="relative flex shrink-0 items-center gap-2 rounded-t-xl bg-green-500 p-2">
    <a href="{{ route('inbox.index') }}" class="flex size-8 shrink-0 items-center justify-center" aria-label="Back">
      <img src="{{ asset('images/inbox/arrow-square-left.svg') }}" alt="" class="size-8" width="32" height="32">
    </a>
    <div class="fd-btn-sm flex size-10 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-500">
      {{ $contact['initials'] }}
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p class="fd-card-title text-base text-text-subtle">{{ $contact['name'] }}</p>
        @if (! empty($contact['stopped']))
          <span class="rounded bg-red-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-700" title="{{ $contact['stop_label'] ?? 'Marked STOP' }}">
            STOP
          </span>
        @endif
      </div>
      @if (! empty($contact['phone']) && $contact['phone'] !== $contact['name'])
        <p class="fd-table-cell truncate text-sm text-white">{{ $contact['phone'] }}</p>
      @endif
      @if (! empty($contact['stopped']))
        <p class="mt-0.5 text-[11px] font-medium text-white/90">{{ $contact['stop_label'] ?? 'This contact marked STOP and is unsubscribed' }}</p>
      @endif
    </div>
    @if ($conversation)
      <div class="flex shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-2 py-1.5 sm:gap-2.5 sm:px-3 sm:py-2">
        <div class="hidden min-w-0 flex-col sm:flex">
          <span class="text-[10px] font-semibold leading-tight text-white sm:text-xs" data-inbox-ai-mode>{{ ! empty($contact['ai_enabled']) ? 'AI' : 'Human' }}</span>
          <span class="text-[10px] leading-tight text-white/75" data-inbox-ai-status>
            {{ ! empty($contact['ai_enabled']) ? 'AI reply' : 'Human reply' }}
          </span>
        </div>
        <x-ui.toggle-switch
          :active="! empty($contact['ai_enabled'])"
          data-inbox-ai-toggle
          aria-label="Toggle AI Reply"
        />
      </div>
      <a
        href="{{ route('inbox.api.export', $conversation) }}"
        data-inbox-export-chat
        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-2 py-1.5 text-xs font-medium text-white transition hover:bg-white/20 sm:px-3 sm:py-2"
      >
        <x-icons.nav-icon name="document-text" class="size-3.5" />
        <span class="hidden sm:inline">Export Chat</span>
      </a>
      <button
        type="button"
        data-inbox-delete-chat
        data-delete-url="{{ route('inbox.api.destroy', $conversation) }}"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-2 py-1.5 text-xs font-medium text-white transition hover:bg-red-500/80 sm:px-3 sm:py-2"
        title="Delete this chat"
      >
        <x-icons.nav-icon name="trash" class="size-3.5 brightness-0 invert" />
        <span class="hidden sm:inline">Delete</span>
      </button>
    @endif
    @if ($conversation && $assignableAgents->isNotEmpty())
      <label class="hidden min-w-0 shrink-0 sm:block">
        <span class="sr-only">Assign agent</span>
        <x-ui.select
          data-inbox-assignee
          variant="header"
          class="max-w-[120px] sm:max-w-[160px]"
        >
          <option value="unassigned" @selected(empty($contact['assignee']))>Unassigned</option>
          @foreach ($assignableAgents as $agent)
            <option value="{{ $agent['key'] }}" @selected(($contact['assignee'] ?? null) === $agent['key'])>{{ $agent['label'] }}</option>
          @endforeach
        </x-ui.select>
      </label>
    @endif
    <button
      type="button"
      data-inbox-minimize
      hidden
      class="hidden flex shrink-0 items-center justify-center gap-2 rounded-lg border border-white/30 bg-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/20"
      title="Restore conversation"
    >
      <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M19 12H5m7-7-7 7 7 7"/>
      </svg>
      Minimize
    </button>
    <button
      type="button"
      data-inbox-maximize
      class="flex shrink-0 items-center justify-center rounded-lg border border-white/30 bg-white/10 p-2 transition hover:bg-white/20"
      aria-label="Maximize conversation"
      aria-pressed="false"
    >
      <img
        src="{{ asset('images/automation/maximize.svg') }}"
        alt=""
        class="size-5 brightness-0 invert"
        width="20"
        height="20"
        data-inbox-maximize-icon
      >
    </button>
  </div>

  <div
    class="relative z-0 flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto overscroll-contain p-4"
    data-inbox-messages
    data-oldest-id="{{ $messagesOldestId ?? '' }}"
    data-has-more="{{ $messagesHasMore ? '1' : '0' }}"
  >
    @foreach ($messages as $message)
      @php
        $isOutbound = ! empty($message['is_outbound']);
        $messageType = (string) ($message['message_type'] ?? 'text');
        $mediaUrl = $message['media_url'] ?? null;
        $fileName = $message['file_name'] ?? null;
        $body = trim((string) ($message['body'] ?? ''));
        $contacts = is_array($message['contacts'] ?? null) ? $message['contacts'] : [];
        $templateCode = trim((string) ($message['template_code'] ?? ''));
        $interactivePreview = is_array($message['interactive_preview'] ?? null)
          ? $message['interactive_preview']
          : null;
      @endphp
      <div
        @class(['flex', 'justify-end' => $isOutbound, 'justify-start' => ! $isOutbound])
        @if (! empty($message['uuid'])) data-message-uuid="{{ $message['uuid'] }}" @endif
        @if (! empty($message['id'])) data-message-id="{{ $message['id'] }}" @endif
      >
        <div @class([
          'max-w-[640px] rounded-bl-[12px] rounded-br-[12px] rounded-tr-[12px] p-4 text-xs leading-[1.8] text-text-body',
          'bg-green-100 rounded-tl-[12px]' => $isOutbound,
          'bg-muted-surface' => ! $isOutbound,
        ]) style="font-family: 'Poppins', var(--font-sans)">
          @if (in_array($messageType, ['image', 'sticker'], true) && $mediaUrl)
            <img src="{{ $mediaUrl }}" alt="{{ $fileName ?: 'Media' }}" class="mb-2 max-h-72 max-w-full rounded-lg object-contain">
            @if ($body !== '')
              <div>{{ $body }}</div>
            @endif
          @elseif ($messageType === 'video' && $mediaUrl)
            <video src="{{ $mediaUrl }}" controls class="mb-2 max-h-72 max-w-full rounded-lg"></video>
            @if ($body !== '')
              <div>{{ $body }}</div>
            @endif
          @elseif ($messageType === 'audio' && $mediaUrl)
            <audio src="{{ $mediaUrl }}" controls class="w-full"></audio>
          @elseif ($messageType === 'document' && $mediaUrl)
            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="font-semibold text-green-700 underline">
              {{ $fileName ?: 'Download document' }}
            </a>
            @if ($body !== '')
              <div class="mt-1">{{ $body }}</div>
            @endif
          @elseif ($messageType === 'location' && isset($message['latitude'], $message['longitude']))
            <a
              href="https://www.google.com/maps?q={{ $message['latitude'] }},{{ $message['longitude'] }}"
              target="_blank"
              rel="noopener"
              class="font-semibold text-green-700 underline"
            >
              View location ({{ $message['latitude'] }}, {{ $message['longitude'] }})
            </a>
          @elseif ($messageType === 'contact' && $contacts !== [])
            <div class="flex flex-col gap-2">
              @foreach ($contacts as $sharedContact)
                @php
                  $contactName = is_array($sharedContact['name'] ?? null)
                    ? (string) ($sharedContact['name']['formatted_name'] ?? $sharedContact['name']['first_name'] ?? 'Contact')
                    : (string) ($sharedContact['name'] ?? ($body !== '' ? $body : 'Contact'));
                  $contactPhone = is_array($sharedContact['phones'][0] ?? null)
                    ? (string) ($sharedContact['phones'][0]['phone'] ?? '')
                    : '';
                  $contactCompany = is_array($sharedContact['org'] ?? null)
                    ? (string) ($sharedContact['org']['company'] ?? '')
                    : '';
                @endphp
                <div class="rounded-lg border border-green-200/80 bg-white/70 px-3 py-2">
                  <div class="text-[10px] font-semibold uppercase tracking-wide text-green-700">Contact</div>
                  <div class="mt-1 font-semibold text-text-subtle">{{ $contactName }}</div>
                  @if ($contactPhone !== '')
                    <div class="text-text-body/70">{{ $contactPhone }}</div>
                  @endif
                  @if ($contactCompany !== '')
                    <div class="text-text-body/60">{{ $contactCompany }}</div>
                  @endif
                </div>
              @endforeach
            </div>
          @elseif ($messageType === 'template')
            @php
              $templateName = trim((string) ($message['template_name'] ?? ''));
              $templateButtons = is_array($message['template_buttons'] ?? null) ? $message['template_buttons'] : [];
              $isProviderCodeBody = $body !== '' && ($body === $templateCode || preg_match('/^[0-9]{10,}$/', $body));
            @endphp
            @if ($body !== '' && ! $isProviderCodeBody)
              <div>{{ $body }}</div>
              @if ($templateButtons !== [])
                <div class="mt-1 flex flex-wrap gap-1.5">
                  @foreach ($templateButtons as $templateButton)
                    <span class="rounded border border-green-300 bg-white/80 px-2 py-1 text-[11px] font-medium text-green-800">
                      {{ is_array($templateButton) ? ($templateButton['text'] ?? '') : $templateButton }}
                    </span>
                  @endforeach
                </div>
              @endif
              <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-green-700">
                {{ $templateName !== '' ? 'Template · '.$templateName : 'Template' }}
              </div>
            @else
              <div class="inline-flex items-center gap-2 rounded-full border border-green-300 bg-white/70 px-3 py-1 text-[11px] font-semibold text-green-800">
                <span>Template</span>
                <span class="font-medium text-text-body">{{ $templateName !== '' ? $templateName : ($templateCode !== '' ? $templateCode : ($body !== '' ? $body : 'message')) }}</span>
              </div>
            @endif
          @elseif ($messageType === 'interactive')
            @php
              $interactiveBody = trim((string) ($interactivePreview['body'] ?? $body));
              $interactiveButtons = is_array($interactivePreview['buttons'] ?? null) ? $interactivePreview['buttons'] : [];
            @endphp
            <div class="flex flex-col gap-2">
              <div>{{ $interactiveBody !== '' ? $interactiveBody : '[interactive]' }}</div>
              @if ($interactiveButtons !== [])
                <div class="mt-1 flex flex-wrap gap-1.5">
                  @foreach ($interactiveButtons as $buttonLabel)
                    <span class="rounded border border-green-300 bg-white/80 px-2 py-1 text-[11px] font-medium text-green-800">
                      {{ $buttonLabel }}
                    </span>
                  @endforeach
                </div>
              @endif
            </div>
          @else
            {{ $body !== '' ? $body : '['.$messageType.']' }}
          @endif
        </div>
      </div>
    @endforeach
  </div>

  <div class="relative z-20 flex shrink-0 flex-col gap-4 p-4">
    <div
      data-inbox-window-banner
      class="hidden rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-900"
      role="status"
    >
      <span data-inbox-window-banner-text>
        Session expired. Send an approved template message to reach this contact again.

When they reply, a 24-hour window opens so you can send normal free-form messages.

Sending a template alone does not start that window — the customer still needs to reply once so the 24-hour window can begin.
      </span>
    </div>

    <form data-inbox-send-form data-inbox-composer data-no-loader class="relative">
      <div class="flex items-center gap-3.5 rounded-lg bg-elevated p-3.5">
        <div class="relative z-50 shrink-0" data-inbox-menu-anchor>
          <button
            type="button"
            data-inbox-menu-toggle
            data-inbox-session-action
            class="flex size-5 cursor-pointer items-center justify-center"
            aria-expanded="{{ $menuOpen ? 'true' : 'false' }}"
            aria-controls="inbox-message-menu"
          >
            <x-icons.nav-icon name="add" class="size-5" />
          </button>
        </div>
        <input
          type="text"
          name="body"
          data-inbox-message-input
          data-inbox-session-action
          placeholder="Type a message"
          class="fd-input min-w-0 flex-1 bg-transparent text-xs placeholder:text-text-body focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
          autocomplete="off"
          required
        >
        <button type="submit" class="shrink-0 text-xs font-semibold text-green-600 disabled:cursor-not-allowed disabled:opacity-50" data-inbox-send-button data-inbox-session-action>Send</button>
      </div>
    </form>

    <div class="flex flex-wrap gap-2.5" data-inbox-expired-actions>
      <x-ui.link-button variant="chat-action" size="sm" class="min-w-0 flex-1 justify-center rounded py-3 text-xs" data-open-modal="send-templates">
        <x-icons.nav-icon name="device-message" class="size-5" />
        Send Template Message
      </x-ui.link-button>
      <button
        type="button"
        data-inbox-resend-opt-in
        class="fd-btn inline-flex min-w-0 flex-1 items-center justify-center gap-2.5 rounded border border-green-500 bg-green-100 px-3 py-3 text-xs font-semibold text-primary-2 transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <x-icons.nav-icon name="device-message" class="size-5" />
        Resend opt-in
      </button>
      <button
        type="button"
        data-open-modal="request-payment"
        class="fd-btn inline-flex min-w-0 flex-1 items-center justify-center gap-2.5 rounded border border-green-500 bg-green-100 px-3 py-3 text-xs font-semibold text-primary-2 transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <x-icons.nav-icon name="wallet" class="size-5" />
        Send Payment Link
      </button>
    </div>
  </div>

  @include('inbox.partials.message-menu', ['menuOpen' => $menuOpen])
</div>
