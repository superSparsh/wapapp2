<x-admin.layout title="Message Performance - Admin" active="admin.message-performance.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Message performance</h1>
    <p class="text-sm text-text-subtle opacity-70">Delivery and read rates by WhatsApp line.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search phone / customer" class="min-w-[180px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected(($filters['tenant'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

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
