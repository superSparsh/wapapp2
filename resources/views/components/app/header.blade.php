<header class="relative z-30 flex h-[84px] shrink-0 items-center justify-between border-b border-border-sidebar bg-elevated px-4">
  <div class="flex min-w-0 flex-1 items-center gap-4">
    <button type="button" data-mobile-menu class="flex size-10 items-center justify-center rounded-md border border-border-sidebar lg:hidden" aria-label="Open menu">
      <img src="{{ asset('images/icons/chevron-right-fd.svg') }}" alt="" class="size-3.5 rotate-180" width="14" height="14">
    </button>
    <div class="relative hidden w-full max-w-[380px] sm:block" data-global-search data-search-url="{{ route('search') }}">
      <form class="flex w-full items-center gap-3 rounded-md border border-border-sidebar bg-elevated px-3 py-3.5" data-global-search-form>
        <x-icons.header-search class="size-6 shrink-0" />
        <input
          type="search"
          name="q"
          placeholder="Search pages or contacts"
          value="{{ request('q') }}"
          class="fd-header-search min-w-0 flex-1 bg-transparent placeholder:text-text-dark focus:outline-none"
          data-global-search-input
          autocomplete="off"
          aria-label="Search pages or contacts"
          aria-expanded="false"
          aria-controls="global-search-results"
        >
      </form>

      <div
        id="global-search-results"
        class="absolute top-[calc(100%+8px)] left-0 z-50 hidden max-h-[360px] w-full overflow-y-auto rounded-lg border border-border bg-elevated shadow-[0px_8px_24px_rgba(0,0,0,0.12)]"
        data-global-search-results
        role="listbox"
      ></div>
    </div>
  </div>

  <div class="relative flex items-center gap-6">
    <button
      type="button"
      data-theme-toggle
      class="relative flex size-9 items-center justify-center rounded-md border border-border-sidebar text-text-dark transition-[background-color,border-color,color,transform] duration-300 hover:bg-surface active:scale-95"
      aria-label="Toggle color theme"
      aria-pressed="false"
    >
      <svg class="size-5 transition-all duration-300 ease-out dark:scale-75 dark:rotate-90 dark:opacity-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3a7 7 0 0 0 11.5 11.5Z"/>
      </svg>
      <svg class="pointer-events-none absolute size-5 scale-75 -rotate-90 opacity-0 transition-all duration-300 ease-out dark:scale-100 dark:rotate-0 dark:opacity-100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
      </svg>
    </button>

    <div class="relative">
      <button
        type="button"
        data-notifications-menu
        class="relative flex h-[37px] w-7 items-center justify-center"
        aria-label="Notifications"
        aria-expanded="false"
      >
        <x-icons.header-bell :count="$notificationCount ?? 0" />
      </button>
      <div id="notifications-panel-backdrop" class="fixed inset-0 z-40 hidden bg-transparent" aria-hidden="true"></div>
      <x-app.notifications-panel />
    </div>

    <button
      type="button"
      data-user-menu
      class="relative shrink-0"
      aria-label="Open account menu"
      aria-expanded="false"
    >
      <x-app.header-avatar />
    </button>

    <div id="user-panel-backdrop" class="fixed inset-0 z-40 hidden bg-overlay" aria-hidden="true"></div>
    <x-app.user-panel />
  </div>
</header>
