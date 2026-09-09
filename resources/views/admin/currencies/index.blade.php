<x-admin.layout title="Currencies - Admin" active="admin.currencies.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Currencies</h1>
      <p class="text-sm text-text-subtle opacity-70">Currencies available for plans and invoices.</p>
    </div>
    <a href="{{ route('admin.currencies.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add currency</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.currencies.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name or code…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'code'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Code', 'Format', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold">{{ $row->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->code }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->format }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'toggle']"
              :links="[
                'edit' => route('admin.currencies.edit', $row),
                'toggle' => route('admin.currencies.toggle', $row),
              ]"
              :methods="['toggle' => 'POST']"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No currencies yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
