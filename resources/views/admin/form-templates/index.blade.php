<x-admin.layout title="Form Templates - Admin" active="admin.form-templates.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Form templates</h1>
      <p class="text-sm text-text-subtle opacity-70">Reusable lead-capture form markup.</p>
    </div>
    <a href="{{ route('admin.form-templates.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add form template</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.form-templates.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name or slug…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Slug', 'Active', 'Updated', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold fd-table-name">{{ $row->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->slug }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->updated_at) }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'trash']"
              :links="[
                'edit' => route('admin.form-templates.edit', $row),
                'trash' => route('admin.form-templates.destroy', $row),
              ]"
              :methods="['trash' => 'DELETE']"
              confirm="Delete this form template? This action cannot be undone."
              confirm-title="Delete form template"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No form templates yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
