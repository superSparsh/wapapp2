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
@endphp

<div
  class="relative flex h-full min-h-0 min-w-0 flex-1 flex-col rounded-xl"
  data-inbox-chat
  @if ($conversation)
    data-conversation-uuid="{{ $conversation->uuid }}"
    data-messages-url="{{ route('inbox.api.messages', $conversation) }}"
    data-send-url="{{ route('inbox.api.send', $conversation) }}"
    data-media-url="{{ route('inbox.api.send-media', $conversation) }}"
    data-template-url="{{ route('inbox.api.send-template', $conversation) }}"
    data-flow-url="{{ route('inbox.api.send-flow', $conversation) }}"
    data-flows-url="{{ route('getflowData') }}"
    data-location-url="{{ route('inbox.api.send-location', $conversation) }}"
    data-sticker-url="{{ route('inbox.api.send-sticker', $conversation) }}"
    data-payment-url="{{ route('inbox.api.request-payment', $conversation) }}"
    data-templates-url="{{ route('inbox.api.templates') }}"
    data-window-url="{{ route('inbox.api.window', $conversation) }}"
    data-window-hours="{{ config('whatsapp.service_window_hours', 24) }}"
    data-read-url="{{ route('inbox.api.read', $conversation) }}"
    data-assign-url="{{ route('inbox.api.assign', $conversation) }}"
    data-export-url="{{ route('inbox.api.export', $conversation) }}"
    data-response-type-url="{{ route('inbox.api.response-type', $conversation) }}"
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
      <p class="fd-card-title text-base text-text-subtle">{{ $contact['name'] }}</p>
      <p class="fd-table-cell truncate text-sm text-white">{{ $contact['phone'] }}</p>
    </div>
    @if ($conversation)
      <div class="hidden shrink-0 items-center gap-2.5 rounded-lg bg-white/10 px-3 py-2 lg:flex">
        <div class="flex min-w-0 flex-col">
          <span class="text-xs font-semibold leading-tight text-white">AI Reply</span>
          <span class="text-[10px] leading-tight text-white/75" data-inbox-ai-status>
            {{ ! empty($contact['ai_enabled']) ? 'AI enabled' : 'Human reply' }}
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
        class="hidden shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-xs font-medium text-white transition hover:bg-white/20 lg:inline-flex"
      >
        <x-icons.nav-icon name="document-text" class="size-3.5" />
        Export Chat
      </a>
    @endif
    @if ($conversation && $assignableAgents->isNotEmpty())
      <label class="hidden shrink-0 lg:block">
        <span class="sr-only">Assign agent</span>
        <x-ui.select
          data-inbox-assignee
          variant="header"
          class="max-w-[160px]"
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

  <div class="relative z-0 flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto overscroll-contain p-4" data-inbox-messages>
    @foreach ($messages as $message)
      <div @class(['flex', 'justify-end' => ! empty($message['is_outbound']), 'justify-start' => empty($message['is_outbound'])])>
        <div @class([
          'max-w-[640px] rounded-bl-[12px] rounded-br-[12px] rounded-tr-[12px] p-4 text-xs leading-[1.8] text-text-body',
          'bg-green-100 rounded-tl-[12px]' => ! empty($message['is_outbound']),
          'bg-muted-surface' => empty($message['is_outbound']),
        ]) style="font-family: 'Poppins', var(--font-sans)">
          {{ $message['body'] }}
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
        24-hour session expired. Send an approved template to re-open the conversation.
      </span>
    </div>

    <form data-inbox-send-form class="relative">
      <div class="flex items-center gap-3.5 rounded-lg bg-elevated p-3.5">
        <div class="relative z-50 shrink-0" data-inbox-menu-anchor>
          <button
            type="button"
            data-inbox-menu-toggle
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

    <div class="flex gap-2.5">
      <x-ui.link-button variant="chat-action" size="sm" class="w-full justify-center rounded py-3 text-xs" data-open-modal="send-templates">
        <x-icons.nav-icon name="device-message" class="size-5" />
        Select and Send a Template Message
      </x-ui.link-button>
      <button
        type="button"
        data-open-modal="request-payment"
        data-inbox-requires-window
        data-inbox-session-action
        class="fd-btn inline-flex w-full items-center justify-center gap-2.5 rounded border border-green-500 bg-green-100 px-3 py-3 text-xs font-semibold text-primary-2 transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <x-icons.nav-icon name="wallet" class="size-5" />
        Send Payment Link
      </button>
    </div>
  </div>

  @include('inbox.partials.message-menu', ['menuOpen' => $menuOpen])
</div>
