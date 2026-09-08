<x-layouts.app title="Calendly Connected - WapApp" active="integration.calendly">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-wrap items-center gap-3 p-4">
      <h1 class="fd-page-title min-w-0 flex-1 text-2xl">Calendly</h1>
      <button
        type="button"
        class="fd-btn inline-flex shrink-0 items-center justify-center rounded border border-red-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-red-500 transition-colors hover:bg-red-50"
      >
        Uninstall Plugin
      </button>
    </div>

    <section class="flex flex-col items-end gap-4 p-4 pt-0">
      <div class="w-full">
        <x-integration.calendly-tabs active="notifications" />
      </div>

      <div class="flex w-full flex-col gap-4 sm:flex-row sm:items-start">
        <div class="flex min-w-0 flex-1 flex-col gap-3">
          <label class="text-sm font-semibold leading-[1.4] text-text-primary">WhatsApp Number (Optional)</label>
          <div class="rounded-xl border border-border bg-elevated p-3.5">
            <span class="text-sm font-medium leading-[1.4] text-text-muted">Enter WhatsApp Number </span>
          </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-3">
          <label class="text-sm font-semibold leading-[1.4] text-text-primary">Calendly Email (Optional)</label>
          <div class="rounded-xl border border-border bg-elevated p-3.5">
            <span class="text-sm font-medium leading-[1.4] text-text-muted">Enter Calendly Email </span>
          </div>
        </div>
      </div>

      <div class="flex w-full items-start gap-3 rounded-xl bg-green-50 p-3.5">
        <div class="p-2">
          <x-ui.toggle-switch :active="true" />
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
          <p class="text-sm font-normal leading-[1.4] text-text-body">Enable WhatsApp Notifications</p>
          <p class="text-xs font-normal leading-[1.4] text-text-body opacity-80">
            Make sure you collect WhatsApp number via a required question in Calendly.
          </p>
        </div>
      </div>

      <button
        type="button"
        class="fd-btn inline-flex shrink-0 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
      >
        Save Settings
      </button>
    </section>
  </div>
</x-layouts.app>
