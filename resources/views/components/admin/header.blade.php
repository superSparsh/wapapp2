<header class="relative z-30 flex h-[84px] shrink-0 items-center justify-between border-b border-border-sidebar bg-elevated px-4">
  <div class="flex min-w-0 flex-1 items-center gap-4">
    <button type="button" data-mobile-menu class="flex size-10 items-center justify-center rounded-md border border-border-sidebar lg:hidden" aria-label="Open menu">
      <img src="{{ asset('images/icons/chevron-right-fd.svg') }}" alt="" class="size-3.5 rotate-180" width="14" height="14">
    </button>
    <div>
      <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">Platform admin</p>
      <p class="text-sm font-semibold text-text-primary">{{ auth('admin')->user()?->name }}</p>
    </div>
  </div>

  <div class="flex items-center gap-3">
    <button
      type="button"
      data-theme-toggle
      class="relative flex size-9 items-center justify-center rounded-md border border-border-sidebar text-text-dark transition hover:bg-surface"
      aria-label="Toggle color theme"
    >
      <svg class="size-5 dark:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
      <svg class="hidden size-5 dark:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/></svg>
    </button>

    <form method="POST" action="{{ route('admin.logout') }}">
      @csrf
      <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-primary hover:bg-surface">
        Logout
      </button>
    </form>
  </div>
</header>
