<x-admin.layout title="Template Gallery - Admin" active="admin.platform-templates.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Template gallery</h1>
      <p class="text-sm text-text-subtle opacity-70">Ready-made message templates offered to customers.</p>
    </div>
    <a href="{{ route('admin.platform-templates.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add template</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.platform-templates.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name, category, type…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[160px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Category</span>
        <select name="category" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All categories</option>
          @foreach ($categories as $option)
            <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Name', 'Category', 'Type', 'Body', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle font-semibold fd-table-name">{{ $row->name }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->category ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->type }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->body, 60) ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'trash']"
              :links="[
                'edit' => route('admin.platform-templates.edit', $row),
                'trash' => route('admin.platform-templates.destroy', $row),
              ]"
              :methods="['trash' => 'DELETE']"
              confirm="Delete this template? This action cannot be undone."
              confirm-title="Delete template"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No templates yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
