@if (! empty($isLineContextLocked) && ! empty($lineContextLockedLine))
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-500/25 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-950">
    <div class="flex min-w-0 items-start gap-2.5">
      <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
        </svg>
      </span>
      <div class="min-w-0">
        <p class="font-semibold leading-snug">
          Number workspace · {{ $lineContextLockedLine->displayPhone() }}
        </p>
        <p class="mt-0.5 text-xs leading-snug text-emerald-900/75">
          Inbox, campaigns, templates, automation, AI, and webhooks work for this number only.
          Security, billing, and WABA settings stay on the main account.
        </p>
      </div>
    </div>
    <form method="POST" action="{{ route('profile.phone-lines.exit-context') }}" class="shrink-0">
      @csrf
      <button
        type="submit"
        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
      >
        {{ ! empty($isLineDirectLogin) ? 'Sign out of number' : 'Exit number mode' }}
      </button>
    </form>
  </div>
@endif
