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
          Your main WhatsApp number lives under <a href="{{ route('profile.integration') }}" class="text-green-600 underline">Integration</a>.
          This page is for <strong>extra numbers</strong> — set a password per number, then open Inbox for that number only.
        </p>
      </div>
      @if (! $isLocked)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <a
            href="{{ $lineLoginUrl }}"
            target="_blank"
            rel="noopener"
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
    <div class="mx-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
      <div class="flex items-start gap-2 text-sm font-medium text-amber-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>
          <strong>You are using one number only:</strong> {{ $lockedLine->displayPhone() }}.
          Inbox shows chats for this number only. Exit to go back to all numbers.
        </span>
      </div>
      <form method="POST" action="{{ route('profile.phone-lines.exit-context') }}">
        @csrf
        <button type="submit" class="fd-btn shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">
          Exit Number Mode
        </button>
      </form>
    </div>
  @endif

  {{-- How it works + share link --}}
  @if (! $isLocked)
    <div class="mx-4 grid gap-3 lg:grid-cols-2">
      <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <div class="text-sm leading-relaxed text-blue-900">
          <p class="font-semibold">How number login works</p>
          <ol class="mt-1 list-decimal space-y-0.5 pl-4 text-blue-800/90">
            <li>Set a Number Access password on the card below.</li>
            <li>Enter that password and click <strong>Open Inbox</strong> — you get a full number workspace (Inbox, campaigns, templates, automation, AI, webhooks) for that line only.</li>
            <li>Or share the login link with an operator so they can sign in with that number + password.</li>
            <li>Security, billing, API, and WABA settings stay on the main account only.</li>
          </ol>
        </div>
      </div>
      <div class="flex items-start gap-3 rounded-xl border border-green-100 bg-green-50 px-4 py-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-5 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
        <div class="min-w-0 flex-1 text-sm leading-relaxed text-green-900">
          <p class="font-semibold">Share this login link</p>
          <p class="mt-1 break-all font-medium text-green-800">{{ $lineLoginUrl }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button
              type="button"
              data-copy-login-link="{{ $lineLoginUrl }}"
              class="inline-flex items-center gap-1 rounded-md border border-green-300 bg-white px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-50"
            >
              Copy link
            </button>
            <a href="{{ $lineLoginUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700">
              Open link
            </a>
          </div>
          <p class="mt-2 text-xs text-green-800/80">Then tell them the WhatsApp number and its Number Access password.</p>
        </div>
      </div>
    </div>
  @endif

  <section class="flex flex-col gap-4 p-4 pt-3">

    @if ($lines->isEmpty())
      <div class="rounded-xl border border-dashed border-border bg-elevated px-6 py-12 text-center">
        <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-green-50 text-green-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        </div>
        <p class="text-base font-semibold text-text-primary">No extra numbers yet</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-text-muted">
          Your primary number is managed under <a href="{{ route('profile.integration') }}" class="text-green-600 underline">Integration</a>.
          Add another WhatsApp number here when you want separate password access for it.
        </p>
        @if ($canAddNumber && ! $isLocked)
          <a href="{{ route('profile.phone-lines.add') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">
            Add Number
          </a>
        @endif
      </div>
    @else
      <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($lines as $line)
          @php
            $isConnected = $line->isConnected();
            $hasPassword = $line->hasLinePassword();
          @endphp
          <article class="relative overflow-hidden rounded-xl border border-border bg-elevated shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-green-500 to-green-700" aria-hidden="true"></div>

            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-divider px-5 py-4 pl-6">
              <div class="min-w-0">
                <p class="text-lg font-semibold tracking-wide text-text-primary">{{ $line->displayPhone() }}</p>
                <p class="mt-0.5 truncate text-xs text-text-muted">{{ $line->display_name ?: 'WhatsApp Business number' }}</p>
              </div>
              <div class="flex flex-wrap items-center justify-end gap-1.5">
                @if ($isConnected)
                  <span class="inline-flex items-center gap-1 rounded-full bg-green-600 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">Connected</span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-red-500 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">Not connected</span>
                @endif
                @if ($hasPassword)
                  <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-semibold text-green-700 ring-1 ring-green-200">Password set</span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-200">Set password</span>
                @endif
              </div>
            </div>

            <div class="flex flex-col gap-5 px-5 py-4 pl-6">
              @if (! $isLocked)
                {{-- Step 1: password --}}
                <div class="flex flex-col gap-2">
                  <div class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-green-100 text-[11px] font-bold text-green-700">1</span>
                    <h3 class="text-sm font-semibold text-text-primary">
                      {{ $hasPassword ? 'Change Number Password' : 'Set Number Password' }}
                    </h3>
                  </div>
                  <p class="pl-8 text-xs text-text-muted">This password is only for this number — not your main account password.</p>
                  <form method="POST" action="{{ route('profile.phone-lines.password') }}" class="grid gap-2 pl-8 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="line" value="{{ $line->uuid }}">
                    <input
                      type="password"
                      name="password"
                      placeholder="New password (min 8)"
                      autocomplete="new-password"
                      minlength="8"
                      required
                      class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                    <input
                      type="password"
                      name="password_confirmation"
                      placeholder="Confirm password"
                      autocomplete="new-password"
                      minlength="8"
                      required
                      class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                    <div class="sm:col-span-2">
                      <button type="submit" class="fd-btn rounded-lg bg-green-500 px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Save Password
                      </button>
                    </div>
                  </form>
                </div>

                <hr class="border-divider">

                {{-- Step 2: open inbox --}}
                <div class="flex flex-col gap-2">
                  <div class="flex items-center gap-2">
                    <span class="flex size-6 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700">2</span>
                    <h3 class="text-sm font-semibold text-text-primary">Open Inbox for this number</h3>
                  </div>
                  <p class="pl-8 text-xs text-text-muted">
                    Enter the password you saved above. Opens a full workspace for <strong>{{ $line->displayPhone() }}</strong> only (Inbox, campaigns, templates, automation — not account security).
                  </p>

                  @if ($hasPassword && $isConnected)
                    <form method="POST" action="{{ route('profile.phone-lines.login-as') }}" class="flex flex-col gap-2 pl-8">
                      @csrf
                      <input type="hidden" name="line" value="{{ $line->uuid }}">
                      <label class="text-xs font-semibold text-text-primary" for="login-pwd-{{ $line->id }}">
                        Password for {{ $line->displayPhone() }}
                      </label>
                      <input
                        id="login-pwd-{{ $line->id }}"
                        type="password"
                        name="password"
                        placeholder="Number access password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-1 focus:ring-green-500"
                      >
                      <button type="submit" class="fd-btn self-start rounded-lg bg-[#0356fb] px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Open Inbox
                      </button>
                    </form>
                  @elseif (! $isConnected)
                    <p class="pl-8 text-xs text-amber-700">Connect this number first before opening its Inbox.</p>
                  @else
                    <div class="pl-8">
                      <input
                        type="password"
                        placeholder="Password for {{ $line->displayPhone() }}"
                        disabled
                        class="w-full cursor-not-allowed rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-muted opacity-60"
                      >
                      <p class="mt-1.5 text-xs text-amber-700">Set a Number Access password in step 1 first.</p>
                      <button type="button" disabled class="mt-2 cursor-not-allowed rounded-lg bg-blue-200 px-4 py-2 text-xs font-semibold text-white opacity-60">
                        Open Inbox
                      </button>
                    </div>
                  @endif
                </div>
              @else
                <p class="text-xs text-text-subtle opacity-70">Password settings are hidden while you are in number mode. Exit number mode to manage them.</p>
              @endif
            </div>
          </article>
        @endforeach
      </div>
    @endif

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

  <script>
    document.querySelectorAll('[data-copy-login-link]').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        var url = btn.getAttribute('data-copy-login-link') || '';
        try {
          await navigator.clipboard.writeText(url);
          var prev = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = prev; }, 1600);
        } catch (e) {
          window.prompt('Copy this link:', url);
        }
      });
    });
  </script>

</x-profile.layout>
