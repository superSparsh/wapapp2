<x-admin.layout title="Message Performance - Admin" active="admin.message-performance.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Message performance</h1>
    <p class="text-sm text-text-subtle opacity-70">Delivery and read rates by WhatsApp line.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.message-performance.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search phone / customer"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'delivery_rate'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Customer</span>
        <select name="tenant" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All customers</option>
          @foreach ($tenants as $tenant)
            <option value="{{ $tenant->id }}" @selected(($filters['tenant'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Line', 'Sent', 'Delivered', 'Read', 'Failed', 'Delivery %', 'Read %']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div>{{ $row['display_name'] ?: '—' }}</div>
            <div class="text-xs text-text-subtle">{{ $row['phone'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['sent'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['delivered'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['read'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['failed'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['delivery_rate'] }}%</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['read_rate'] }}%</td>
        </tr>
      @empty
        <tr><td colspan="8" class="p-6 text-center text-sm text-text-subtle">No performance data.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
