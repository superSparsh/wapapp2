@props([
    'show' => false,
    'closeHref' => null,
    'failedCount' => 0,
    'campaignName' => '',
    'suggestedListName' => '',
])

@php
    $closeUrl = $closeHref ?? url()->current();
@endphp

@if ($show)
  <div
    class="fixed inset-0 z-[100] flex items-center justify-center overflow-hidden bg-black/60 p-3 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="resend-failed-title"
    data-resend-modal-overlay
  >
    <div data-resend-modal-shell class="overflow-hidden">
      <div
        data-resend-modal-panel
        class="flex w-[min(681px,calc(100vw-1.5rem))] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]"
      >
        <div class="flex shrink-0 items-start justify-end gap-4">
          <div class="flex min-w-0 flex-1 flex-col gap-1">
            <h2 id="resend-failed-title" class="text-2xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">
              Resend Failed Contacts
            </h2>
            <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">
              Resend Messages
            </p>
          </div>
          <a href="{{ $closeUrl }}" class="relative size-6 shrink-0" aria-label="Close">
            <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
          </a>
        </div>

        <div class="flex shrink-0 items-start gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
          <div class="flex min-w-0 flex-1 flex-col gap-2.5 text-sm" style="font-family: var(--font-display)">
            <p class="font-bold leading-[1.4] text-text-body">Failed Contacts Summary:</p>
            <ul class="list-disc space-y-0 pl-[21px] font-normal leading-[1.4] text-text-muted">
              <li>Total Failed Contacts:&nbsp;{{ $failedCount }}</li>
              @if ($campaignName)
                <li>Original Campaign:&nbsp;{{ $campaignName }}</li>
              @endif
              <li>This will create a new list and campaign for resending</li>
            </ul>
          </div>
        </div>

        <div class="flex w-full shrink-0 flex-col gap-8 rounded-xl border border-border-light bg-muted-surface p-4">
          <div class="flex flex-col gap-2">
            <label class="fd-label" for="resend-list-name">New List Name</label>
            <div class="flex items-center rounded-xl border border-border bg-elevated p-3.5">
              <input
                id="resend-list-name"
                type="text"
                value="{{ $suggestedListName }}"
                placeholder="Enter list name"
                class="w-full border-0 bg-transparent p-0 text-sm font-medium leading-[1.4] text-text-muted outline-none"
                style="font-family: var(--font-display)"
              >
            </div>
            <p class="text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">
              This will be the name of the new list containing failed contacts
            </p>
          </div>

          <div class="flex flex-col gap-2">
            <label class="fd-label" for="resend-campaign-name">New Campaign Name</label>
            <div class="flex items-center rounded-xl border border-border bg-elevated p-3.5">
              <input
                id="resend-campaign-name"
                type="text"
                placeholder="Campaign Name"
                class="w-full border-0 bg-transparent p-0 text-sm font-medium leading-[1.4] text-text-muted outline-none placeholder:text-text-muted"
                style="font-family: var(--font-display)"
              >
            </div>
            <p class="text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">
              This will be the name of the new campaign for resending
            </p>
          </div>

          <fieldset class="flex flex-col gap-2 border-0 p-0" data-resend-options>
            <legend class="fd-label mb-0 px-0">Send options</legend>
            <label class="inline-flex cursor-pointer items-center gap-2" data-resend-option>
              <input type="radio" name="resend_send_option" value="schedule" class="sr-only" checked>
              <img src="{{ asset('images/auth/radio-checked.svg') }}" alt="" class="size-4 shrink-0" data-radio-icon width="16" height="16">
              <span class="text-sm font-medium leading-[1.4] text-green-500" data-radio-label style="font-family: var(--font-display)">Schedule for Later</span>
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2" data-resend-option>
              <input type="radio" name="resend_send_option" value="now" class="sr-only">
              <img src="{{ asset('images/auth/radio-unchecked.svg') }}" alt="" class="size-4 shrink-0" data-radio-icon width="16" height="16">
              <span class="text-sm font-medium leading-[1.4] text-text-primary" data-radio-label style="font-family: var(--font-display)">Send now</span>
            </label>
          </fieldset>
        </div>

        <div class="flex shrink-0 items-start gap-3 rounded-xl bg-stat-orange/15 p-3.5">
          <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
          <div class="flex min-w-0 flex-1 flex-col gap-2.5 text-sm" style="font-family: var(--font-display)">
            <p class="font-bold leading-[1.4] text-text-body">Important:</p>
            <p class="font-normal leading-[1.4] text-text-muted">
              This action will create a new list and campaign. The original campaign template and settings will be copied to the new campaign.
            </p>
          </div>
        </div>

        <div class="flex shrink-0 items-center justify-end">
          <button
            type="button"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2"
            style="font-family: var(--font-display)"
          >
            <x-icons.nav-icon name="send" class="size-5" />
            Create &amp; Resend
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (() => {
      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');

      const overlay = document.querySelector('[data-resend-modal-overlay]');
      const shell = document.querySelector('[data-resend-modal-shell]');
      const panel = document.querySelector('[data-resend-modal-panel]');
      if (!overlay || !shell || !panel) return;

      const fitToViewport = () => {
        panel.style.transform = 'none';
        panel.style.transformOrigin = 'top left';
        shell.style.width = 'auto';
        shell.style.height = 'auto';

        const naturalW = panel.offsetWidth;
        const naturalH = panel.offsetHeight;
        const availableW = Math.max(0, overlay.clientWidth - 24);
        const availableH = Math.max(0, overlay.clientHeight - 24);
        const scale = Math.min(1, availableW / naturalW, availableH / naturalH);

        panel.style.transform = `scale(${scale})`;
        shell.style.width = `${naturalW * scale}px`;
        shell.style.height = `${naturalH * scale}px`;
      };

      fitToViewport();
      requestAnimationFrame(fitToViewport);
      window.addEventListener('resize', fitToViewport);

      const root = document.querySelector('[data-resend-options]');
      if (!root) return;
      const checkedSrc = @json(asset('images/auth/radio-checked.svg'));
      const uncheckedSrc = @json(asset('images/auth/radio-unchecked.svg'));
      const sync = () => {
        root.querySelectorAll('[data-resend-option]').forEach((option) => {
          const input = option.querySelector('input[type="radio"]');
          const icon = option.querySelector('[data-radio-icon]');
          const label = option.querySelector('[data-radio-label]');
          const on = input?.checked;
          if (icon) icon.src = on ? checkedSrc : uncheckedSrc;
          if (label) {
            label.classList.toggle('text-green-500', !!on);
            label.classList.toggle('text-text-primary', !on);
          }
        });
      };
      root.addEventListener('change', sync);
      sync();
    })();
  </script>
@endif
