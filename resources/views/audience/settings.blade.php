<x-layouts.app title="Audience Settings - WapApp" active="audience.settings">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4">
      <x-audience.list-header :title="$mailList?->name ?? 'Settings'" :subscribers="(string) ($mailList?->totalContactsCount() ?? 0)" />
      <x-audience.sub-nav active="audience.settings" />
    </div>

    @if($mailList)
    <form method="POST" action="{{ route('audience.lists.update', $mailList) }}" class="flex flex-col gap-4 p-4 pt-5">
      @csrf
      @method('PUT')
      <section class="rounded-lg bg-elevated p-3">
        <div class="flex max-w-[564px] flex-col gap-2">
          <label for="list_name" class="text-base font-semibold leading-[1.4] text-text-primary">Edit your list Name</label>
          <input
            id="list_name"
            name="name"
            type="text"
            value="{{ old('name', $mailList->name) }}"
            placeholder="Enter List Name"
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          >
        </div>
      </section>

      <div class="flex justify-end">
        <button type="submit" class="fd-btn rounded bg-green-500 px-6 py-3 text-xs font-semibold text-primary-2 transition-colors hover:opacity-90">
          Save
        </button>
      </div>
    </form>
    @else
    <section class="p-4 pt-5">
      <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-8 text-center">
        <p class="text-sm text-text-body/70">No list selected. Please select a list from the overview page.</p>
      </div>
    </section>
    @endif
  </div>
</x-layouts.app>
