<x-admin.layout title="Country Pricing - Admin" active="admin.pricing.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Country pricing</h1>
      <p class="text-sm text-text-subtle opacity-70">Per-country conversation rates.</p>
    </div>
    <a href="{{ route('admin.pricing.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Add country</a>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('admin.pricing.import') }}" enctype="multipart/form-data" class="mx-4 mb-4 flex flex-wrap items-end gap-3 rounded-[20px] border border-border bg-elevated p-4">
    @csrf
    <label class="flex min-w-[220px] flex-1 flex-col gap-1.5 text-sm">
      <span class="font-semibold">Import CSV</span>
      <input type="file" name="csv" accept=".csv,text/csv" required class="rounded-lg border border-border px-3 py-2 text-sm">
    </label>
    <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Upload</button>
  </form>

  <x-admin.filter-bar
    :action="route('admin.pricing.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search country name or code…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'country_name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Country', 'Marketing', 'Utility', 'Auth', 'Service', 'Active', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->country_name }}</div>
            <div class="text-xs text-text-subtle">{{ $row->country_code }} · {{ $row->currency }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->marketing_rate }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->utility_rate }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->authentication_rate }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->service_rate }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->is_active ? 'Yes' : 'No' }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['edit', 'toggle']"
              :links="[
                'edit' => route('admin.pricing.edit', $row),
                'toggle' => route('admin.pricing.toggle', $row),
              ]"
              :methods="['toggle' => 'POST']"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No pricing rows yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
