<x-admin.layout title="Data Purge - Admin" active="admin.data-purge.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Expired data purge</h1>
    <p class="text-sm text-text-subtle opacity-70">Suspended, expired, or purge-marked customers.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search" class="flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

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
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['status'] }}</td>
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
