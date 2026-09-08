@php
  $adminUser = auth('admin')->user();
@endphp

<header class="relative z-30 flex h-[84px] shrink-0 items-center justify-between border-b border-border-sidebar bg-elevated px-4">
  <div class="flex min-w-0 flex-1 items-center gap-4">
    <button type="button" data-mobile-menu class="flex size-10 items-center justify-center rounded-md border border-border-sidebar lg:hidden" aria-label="Open menu">
      <img src="{{ asset('images/icons/chevron-right-fd.svg') }}" alt="" class="size-3.5 rotate-180" width="14" height="14">
    </button>
    <div>
      <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">Platform admin</p>
      <p class="text-sm font-semibold text-text-primary">{{ $adminUser?->name }}</p>
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

    {{-- Legacy admin user menu: Customer View + My Profile (no direct logout) --}}
    <div class="relative" data-admin-user-menu>
      <button
        type="button"
        data-admin-user-menu-toggle
        class="flex max-w-[220px] items-center gap-2 rounded-lg border border-border-sidebar px-2.5 py-1.5 text-left transition hover:bg-surface"
        aria-expanded="false"
        aria-haspopup="true"
      >
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-xs font-bold text-green-700">
          {{ strtoupper(substr((string) ($adminUser?->name ?? 'A'), 0, 1)) }}
        </span>
        <span class="min-w-0 truncate text-sm font-semibold text-text-primary">{{ $adminUser?->name }}</span>
        <img src="{{ asset('images/icons/chevron-right-fd.svg') }}" alt="" class="size-3 rotate-90 opacity-60" width="12" height="12">
      </button>

      <div
        data-admin-user-menu-panel
        class="absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[280px] overflow-hidden rounded-2xl border border-border bg-elevated shadow-[0px_8px_24px_rgba(0,0,0,0.12)]"
        role="menu"
      >
        <div class="border-b border-border px-4 py-3">
          <p class="truncate text-sm font-semibold text-text-primary">{{ $adminUser?->name }}</p>
          <p class="truncate text-xs text-text-subtle">{{ $adminUser?->email }}</p>
        </div>

        <nav class="flex flex-col p-2">
          <a
            href="{{ route('admin.customer-view') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-primary transition hover:bg-surface"
            role="menuitem"
          >
            <span class="flex size-8 items-center justify-center rounded-md bg-green-50 text-xs font-bold text-green-700">CV</span>
            <span>Customer View</span>
          </a>
          <a
            href="{{ route('admin.account.profile') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-primary transition hover:bg-surface"
            role="menuitem"
          >
            <span class="flex size-8 items-center justify-center rounded-md bg-surface text-xs font-bold text-text-subtle">MP</span>
            <span>My Profile</span>
          </a>
        </nav>
      </div>
    </div>
  </div>
</header>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-admin-user-menu]');
    if (!root) return;
    const toggle = root.querySelector('[data-admin-user-menu-toggle]');
    const panel = root.querySelector('[data-admin-user-menu-panel]');
    if (!toggle || !panel) return;

    const close = () => {
      panel.classList.add('hidden');
      toggle.setAttribute('aria-expanded', 'false');
    };
    const open = () => {
      panel.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    };

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      panel.classList.contains('hidden') ? open() : close();
    });
    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });
  });
</script>
