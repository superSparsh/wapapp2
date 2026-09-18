<x-admin.layout title="Pricing change logs - Admin" active="admin.pricing.logs">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.pricing.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Country pricing</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">Pricing change logs</h1>
      <p class="text-sm text-text-subtle opacity-70">Audit trail for Meta / Tekpro price updates.</p>
    </div>
  </div>

  <x-admin.filter-bar
    :action="route('admin.pricing.logs')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search country or conversation…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'created_at'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['When', 'Country', 'Conversation', 'Old', 'New', 'By']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle text-sm">{{ optional($row->created_at)->toDayDateTimeString() }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row->country_code }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->conversation }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->old_price ?? '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->new_price ?? '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->admin?->name ?? ('#'.$row->updated_by) }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No price changes logged yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
