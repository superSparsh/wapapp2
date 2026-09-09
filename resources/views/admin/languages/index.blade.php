<x-admin.layout title="Languages - Admin" active="admin.languages.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Languages</h1>
      <p class="text-sm text-text-subtle opacity-70">Locales customers can pick for their workspace.</p>
    </div>
    <a href="{{ route('admin.languages.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add language</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.languages.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name or code…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Code', 'Region', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold fd-table-name">{{ $row->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->code }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->region_code ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[160px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'toggle', 'trash']"
              :links="[
                'edit' => route('admin.languages.edit', $row),
                'toggle' => route('admin.languages.toggle', $row),
                'trash' => route('admin.languages.destroy', $row),
              ]"
              :methods="['toggle' => 'POST', 'trash' => 'DELETE']"
              confirm="Delete this language? This action cannot be undone."
              confirm-title="Delete language"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No languages yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
