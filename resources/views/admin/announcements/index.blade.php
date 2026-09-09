<x-admin.layout title="Announcements - Admin" active="admin.announcements.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Announcements</h1>
      <p class="text-sm text-text-subtle opacity-70">Platform-wide notices for tenants.</p>
    </div>
    <a href="{{ route('admin.announcements.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">New announcement</a>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <x-admin.filter-bar
    :action="route('admin.announcements.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search title or body…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Title', 'Active', 'Window', 'Actions']" :paginator="$announcements">
      @forelse ($announcements as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->title }}</div>
            <div class="line-clamp-1 text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->body, 80) }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">
            {{ format_ist($row->starts_at, 'd M Y') }} → {{ format_ist($row->ends_at, 'd M Y') }}
          </td>
          <td class="w-[160px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'toggle', 'trash']"
              :links="[
                'edit' => route('admin.announcements.edit', $row),
                'toggle' => route('admin.announcements.toggle', $row),
                'trash' => route('admin.announcements.destroy', $row),
              ]"
              :methods="['toggle' => 'POST', 'trash' => 'DELETE']"
              confirm="Delete this announcement? This action cannot be undone."
              confirm-title="Delete announcement"
              confirm-label="Delete"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="p-6 text-center text-sm text-text-subtle">No announcements yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
