<x-layouts.app title="Blacklist - WapApp" active="audience.blacklist">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Blacklist</h1>
        <p class="fd-page-note">Blocked phones and emails are skipped on import and cannot be added as subscribers.</p>
      </div>

      <x-ui.listing-toolbar
        :action="route('audience.blacklist')"
        :search-value="request('search')"
        :show-sort="false"
      >
        <x-slot:actions>
          <button type="button" data-open-modal="add-blacklist" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
            Add entry
          </button>
          <button type="button" data-open-modal="import-blacklist" class="fd-btn inline-flex items-center justify-center gap-2 rounded-lg border border-green-500 bg-green-100 px-4 py-3 text-sm font-semibold text-green-500">
            Import CSV
          </button>
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="p-4 pt-0">
      <x-ui.data-table :headers="['SI. No', 'Phone', 'Email', 'Reason', 'Added', 'Actions']" :paginator="$entries">
        @forelse ($entries as $i => $entry)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $entries->firstItem() + $i }}</td>
            <td class="fd-table-cell p-2">{{ $entry->phone ?: '—' }}</td>
            <td class="fd-table-cell p-2">{{ $entry->email ?: '—' }}</td>
            <td class="fd-table-cell p-2">{{ $entry->reason ?: '—' }}</td>
            <td class="fd-table-cell p-2">{{ $entry->created_at?->format('d M Y H:i') ?? '—' }}</td>
            <td class="p-2">
              <form method="POST" action="{{ route('audience.blacklist.destroy', $entry) }}" class="inline" data-confirm="Remove this blacklist entry?" data-confirm-title="Remove entry" data-confirm-label="Remove">
                @csrf
                @method('DELETE')
                <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete" title="Delete">
                  <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr class="bg-elevated">
            <td colspan="6" class="p-8 text-center text-sm text-text-body/70">No blacklist entries yet.</td>
          </tr>
        @endforelse
      </x-ui.data-table>
    </section>
  </div>

  <div id="modal-add-blacklist" data-modal="add-blacklist" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
    <div class="w-full max-w-md rounded-[20px] bg-elevated p-5 shadow-lg">
      <div class="mb-4 flex items-start justify-between">
        <h2 class="text-xl font-bold text-text-primary">Add to blacklist</h2>
        <button type="button" data-modal-close class="text-text-muted">&times;</button>
      </div>
      <form method="POST" action="{{ route('audience.blacklist.store') }}" class="space-y-4">
        @csrf
        <div>
          <label class="mb-1 block text-sm font-semibold">Phone</label>
          <input name="phone" type="text" placeholder="919876543210" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
        </div>
        <div>
          <label class="mb-1 block text-sm font-semibold">Email</label>
          <input name="email" type="email" placeholder="spam@example.com" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
        </div>
        <div>
          <label class="mb-1 block text-sm font-semibold">Reason</label>
          <input name="reason" type="text" placeholder="Optional" class="w-full rounded-xl border border-border bg-elevated px-3 py-3 text-sm">
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" data-modal-close class="rounded border border-green-500 px-4 py-2 text-sm font-semibold text-green-500">Cancel</button>
          <button type="submit" class="rounded bg-green-500 px-4 py-2 text-sm font-semibold text-primary-2">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div id="modal-import-blacklist" data-modal="import-blacklist" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4">
    <div class="w-full max-w-md rounded-[20px] bg-elevated p-5 shadow-lg">
      <div class="mb-4 flex items-start justify-between">
        <h2 class="text-xl font-bold text-text-primary">Import blacklist CSV</h2>
        <button type="button" data-modal-close class="text-text-muted">&times;</button>
      </div>
      <form method="POST" action="{{ route('audience.blacklist.import') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <p class="text-sm text-text-muted">CSV headers: <code>phone</code>, <code>email</code>, <code>reason</code></p>
        <input type="file" name="file" accept=".csv,.txt" required class="w-full text-sm">
        <div class="flex justify-end gap-2">
          <button type="button" data-modal-close class="rounded border border-green-500 px-4 py-2 text-sm font-semibold text-green-500">Cancel</button>
          <button type="submit" class="rounded bg-green-500 px-4 py-2 text-sm font-semibold text-primary-2">Import</button>
        </div>
      </form>
    </div>
  </div>
</x-layouts.app>
