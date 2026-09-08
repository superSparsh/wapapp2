<x-layouts.app title="Subscribers - WapApp" active="audience.subscribers">
  <div class="flex flex-col">
    <x-audience.list-header title="Subscribers" subscribers="0" />
    <x-audience.sub-nav active="audience.subscribers" />

    <section class="p-4 pt-0">
      <div class="flex flex-col items-center justify-center rounded-lg border border-[0.5px] border-border-light bg-elevated py-16 text-center">
        <div class="flex size-16 items-center justify-center rounded-full bg-green-50">
          <x-icons.nav-icon name="user-add" class="size-8 text-green-500" />
        </div>
        <h3 class="fd-section-title mt-4">No subscribers yet</h3>
        <p class="fd-page-note mt-2 max-w-sm text-[13px] text-text-body/70 opacity-100">Import contacts from a CSV file or create an opt-in form to start building your audience.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <button type="button" data-open-modal="import-subscribers" class="fd-btn inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-3 py-2.5 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
            <x-icons.nav-icon name="document-text" class="size-4" />
            Import CSV
          </button>
          <x-ui.link-button href="{{ route('audience.forms') }}" variant="outline" size="sm">Create Form</x-ui.link-button>
        </div>
      </div>
    </section>
  </div>

  <x-audience.import-subscribers-modal />
</x-layouts.app>
