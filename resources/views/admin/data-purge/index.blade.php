<x-admin.layout title="Data Purge - Admin" active="admin.data-purge.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Expired data purge</h1>
    <p class="text-sm text-text-subtle opacity-70">Suspended, expired, or purge-marked customers.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.data-purge.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Reason', 'Valid until', 'Status', 'Actions']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row['name'] }}</div>
            <div class="text-xs text-text-subtle">{{ $row['email'] ?: $row['id'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['reason'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['valid_until'] ? format_ist($row['valid_until'], 'd M Y') : '—' }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row['status']" /></td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['eye']"
              :links="['eye' => route('admin.data-purge.show', $row['id'])]"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No purge candidates.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
