<x-profile.layout title="Manage Phone Numbers - WapApp" headerTitle="Phone Numbers" active="profile.phone-lines.index">

  {{-- Flash messages --}}
  @if (session('success'))
    <div class="mx-4 mt-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
      {{ session('success') }}
    </div>
  @endif
  @if (session('error'))
    <div class="mx-4 mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ session('error') }}
    </div>
  @endif
  @if ($errors->any())
    <div class="mx-4 mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="flex flex-col p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Manage Phone Numbers</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Your main WhatsApp number is managed under <a href="{{ route('profile.integration') }}" class="text-green-600 underline">Integration</a>.
          Here you can manage additional numbers — set a Number Access password and open Inbox for each one.
        </p>
      </div>
      {{-- Header action buttons --}}
      @if (! $isLocked)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <a
            href="{{ $lineLoginUrl }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-primary hover:bg-surface"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
            Open Login Link
          </a>
          @if ($canAddNumber)
            <a
              href="{{ route('profile.phone-lines.add') }}"
              class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Number
            </a>
          @else
            <button
              type="button"
              disabled
              title="Connect WhatsApp in Integration first before adding more numbers."
              class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg bg-green-200 px-3 py-2 text-xs font-semibold text-white opacity-60"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Number
            </button>
          @endif
        </div>
      @endif
    </div>
  </div>

  {{-- Locked-line banner --}}
  @if ($isLocked && $lockedLine)
    <div class="mx-4 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
      <div class="flex items-center gap-2 text-sm font-medium text-amber-800">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>You are in number-specific access mode for <strong>{{ $lockedLine->displayPhone() }}</strong>. Inbox and campaigns show data for this number only. Some actions are disabled.</span>
      </div>
      <form method="POST" action="{{ route('profile.phone-lines.exit-context') }}">
        @csrf
        <button type="submit" class="fd-btn shrink-0 rounded bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">
          Exit Number Mode
        </button>
      </form>
    </div>
  @endif

  {{-- Share login link info banner (only when not locked) --}}
  @if (! $isLocked)
    <div class="mx-4 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
      <p class="text-sm text-blue-800">
        To give someone access to one number only, share this link:
        <a href="{{ $lineLoginUrl }}" class="font-semibold underline break-all">{{ $lineLoginUrl }}</a>.
        Then tell them the number and its Number Access password.
      </p>
    </div>
  @endif

  <section class="flex flex-col gap-4 p-4 pt-2">

    @if ($lines->isEmpty())
      <div class="rounded-lg bg-elevated p-8 text-center">
        <p class="text-sm text-text-muted">No additional phone numbers found.</p>
        <p class="mt-1 text-xs text-text-subtle opacity-60">
          Your primary number is managed under <a href="{{ route('profile.integration') }}" class="text-green-600 underline">Integration</a>.
          Contact support to add more numbers to your account.
        </p>
      </div>
    @else
      @foreach ($lines as $line)
        @php
          $isConnected    = $line->isConnected();
          $hasPassword    = $line->hasLinePassword();
          $cardId         = 'line-' . $line->id;
        @endphp
        <div class="overflow-hidden rounded-xl border border-border bg-elevated shadow-sm">

          {{-- Card header --}}
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-divider bg-elevated px-4 py-3">
            <div class="flex items-center gap-3">
              <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.63 19.79 19.79 0 01.05 2.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
              </div>
              <div>
                <p class="text-base font-semibold text-text-primary">{{ $line->displayPhone() }}</p>
                @if ($line->display_name)
                  <p class="text-xs text-text-muted">{{ $line->display_name }}</p>
                @endif
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              @if ($isConnected)
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Connected
                </span>
              @else
                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-600">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Not Connected
                </span>
              @endif
              {{-- Password status badge --}}
              @if ($hasPassword)
                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                  Password set
                </span>
              @else
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                  <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                  No password
                </span>
              @endif
              @if ($line->quality_rating)
                <span class="rounded-full bg-elevated px-2.5 py-1 text-xs font-medium text-text-body ring-1 ring-border">
                  Quality: {{ strtoupper($line->quality_rating) }}
                </span>
              @endif
              @if ($line->messaging_limit_tier)
                <span class="rounded-full bg-elevated px-2.5 py-1 text-xs font-medium text-text-body ring-1 ring-border">
                  {{ $line->messaging_limit_tier }}
                </span>
              @endif
            </div>
          </div>

          {{-- Card body --}}
          <div class="grid gap-6 p-4 md:grid-cols-2">

            {{-- Set as Default --}}
            <div class="flex flex-col gap-3">
              <div>
                <h3 class="text-sm font-semibold text-text-primary">Set as Default</h3>
                <p class="mt-0.5 text-xs text-text-muted">Make this the primary number used for Inbox and campaigns.</p>
              </div>
              @if (! $isLocked && $isConnected)
                <form method="POST" action="{{ route('profile.phone-lines.set-default') }}">
                  @csrf
                  <input type="hidden" name="line_id" value="{{ $line->id }}">
                  <button type="submit" class="fd-btn inline-flex items-center gap-1.5 rounded border border-border px-3 py-2 text-xs font-semibold text-text-primary hover:bg-surface">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-amber-500" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Set as Default
                  </button>
                </form>
              @elseif ($isLocked)
                <p class="text-xs text-text-subtle opacity-60">Disabled in number-specific access mode.</p>
              @else
                <p class="text-xs text-text-subtle opacity-60">Connect this number first before setting it as default.</p>
              @endif
            </div>

            {{-- Number Access Password + Login-as --}}
            <div class="flex flex-col gap-3">
              <div>
                <h3 class="text-sm font-semibold text-text-primary">
                  {{ $hasPassword ? 'Change Number Password' : 'Set Number Password' }}
                </h3>
                <p class="mt-0.5 text-xs text-text-muted">
                  Set a password to access Inbox specifically for this number.
                </p>
              </div>

              @if (! $isLocked)
                <form method="POST" action="{{ route('profile.phone-lines.password') }}" class="flex flex-col gap-2">
                  @csrf
                  <input type="hidden" name="line_id" value="{{ $line->id }}">
                  <input
                    type="password"
                    name="password"
                    placeholder="New password (min 8 chars)"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="w-full rounded-lg border border-border bg-elevated px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                  <input
                    type="password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    class="w-full rounded-lg border border-border bg-elevated px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                  <button type="submit" class="fd-btn self-start rounded bg-green-500 px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                    Save Password
                  </button>
                </form>

                @if ($hasPassword && $isConnected)
                  <hr class="border-divider">
                  <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-text-primary">Open Inbox for this Number</h3>
                    <p class="text-xs text-text-muted">Enter your number password to lock the session to this line's Inbox.</p>
                    <form method="POST" action="{{ route('profile.phone-lines.login-as') }}" class="flex flex-col gap-2">
                      @csrf
                      <input type="hidden" name="line_id" value="{{ $line->id }}">
                      <input
                        type="password"
                        name="password"
                        placeholder="Password for {{ $line->displayPhone() }}"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-border bg-elevated px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                      >
                      <button type="submit" class="fd-btn self-start rounded bg-blue-500 px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Open Inbox
                      </button>
                    </form>
                  </div>
                @elseif ($isConnected)
                  <hr class="border-divider">
                  <div class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-text-primary">Open Inbox for this Number</h3>
                    <p class="text-xs text-text-muted">Enter your number password to lock the session to this line's Inbox.</p>
                    <input
                      type="password"
                      placeholder="Password for {{ $line->displayPhone() }}"
                      disabled
                      class="w-full cursor-not-allowed rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-muted opacity-60"
                    >
                    <p class="text-xs text-amber-600">Set a Number Access password above first before you can open the Inbox for this number.</p>
                    <button type="button" disabled class="fd-btn self-start cursor-not-allowed rounded bg-blue-200 px-4 py-2 text-xs font-semibold text-white opacity-60">
                      Open Inbox
                    </button>
                  </div>
                @endif
              @else
                <p class="text-xs text-text-subtle opacity-60">Password settings are hidden while in number-specific access mode. Exit to manage them.</p>
              @endif
            </div>

          </div>
        </div>
      @endforeach
    @endif

    {{-- Meta guideline note --}}
    <div class="flex w-full items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
      <svg xmlns="http://www.w3.org/2000/svg" class="size-6 shrink-0 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div class="flex min-w-0 flex-1 flex-col gap-1.5 text-sm leading-[1.4]">
        <p class="font-bold text-text-body">Note:</p>
        <p class="font-normal text-text-muted">
          As per Meta's WhatsApp Business platform guidelines, business accounts are initially limited to <strong>2 registered business phone numbers</strong>.
          This limit can be increased to up to 20. Contact your account manager to add new numbers.
        </p>
      </div>
    </div>

  </section>

</x-profile.layout>
