<x-admin.layout title="Plugins - Admin" active="admin.plugins.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Plugins</h1>
    <p class="text-sm text-text-subtle opacity-70">Optional modules that can be switched on per platform.</p>
  </div>

  <form method="POST" action="{{ route('admin.plugins.store') }}" class="mx-4 mb-4 flex flex-wrap items-end gap-3 rounded-[20px] border border-border bg-elevated p-4">
    @csrf
    <label class="flex min-w-[180px] flex-1 flex-col gap-1.5 text-sm">
      <span class="font-semibold">Title</span>
      <input name="title" value="{{ old('title') }}" required class="rounded-lg border border-border px-3 py-2 text-sm">
    </label>
    <label class="flex min-w-[140px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Key</span>
      <input name="name" value="{{ old('name') }}" class="rounded-lg border border-border px-3 py-2 text-sm" placeholder="auto">
    </label>
    <label class="flex min-w-[120px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Type</span>
      <input name="type" value="{{ old('type', 'general') }}" class="rounded-lg border border-border px-3 py-2 text-sm">
    </label>
    <label class="flex min-w-[100px] flex-col gap-1.5 text-sm">
      <span class="font-semibold">Version</span>
      <input name="version" value="{{ old('version') }}" class="rounded-lg border border-border px-3 py-2 text-sm" placeholder="1.0.0">
    </label>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-xs font-semibold text-white">Register plugin</button>
  </form>

  @if ($errors->any())
    <div class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
  @endif

  <x-admin.filter-bar
    :action="route('admin.plugins.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search title, key, type…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'title'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Plugin', 'Type', 'Version', 'Enabled', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->title }}</div>
            <div class="text-xs text-text-subtle">{{ $row->name }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->type }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->version ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_enabled ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['toggle', 'trash']"
              :links="[
                'toggle' => route('admin.plugins.toggle', $row),
                'trash' => route('admin.plugins.destroy', $row),
              ]"
              :methods="['toggle' => 'POST', 'trash' => 'DELETE']"
              confirm="Remove this plugin? This action cannot be undone."
              confirm-title="Remove plugin"
              confirm-label="Remove"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No plugins registered.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
