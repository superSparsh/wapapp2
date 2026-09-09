<x-admin.layout title="Page Layouts - Admin" active="admin.page-layouts.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Page layouts</h1>
      <p class="text-sm text-text-subtle opacity-70">Static marketing and legal page content.</p>
    </div>
    <a href="{{ route('admin.page-layouts.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add page layout</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.page-layouts.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name or slug…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Slug', 'Alias', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold fd-table-name">{{ $row->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->slug }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->alias ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'trash']"
              :links="[
                'edit' => route('admin.page-layouts.edit', $row),
                'trash' => route('admin.page-layouts.destroy', $row),
              ]"
              :methods="['trash' => 'DELETE']"
              confirm="Delete this page layout? This action cannot be undone."
              confirm-title="Delete page layout"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No page layouts yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
