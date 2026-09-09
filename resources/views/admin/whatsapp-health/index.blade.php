<x-admin.layout title="WhatsApp Health - Admin" active="admin.whatsapp-health.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">WhatsApp Health</h1>
    <p class="text-sm text-text-subtle opacity-70">Fleet overview from live line quality and message outcomes.</p>
  </div>

  <section class="grid gap-3 p-4 pt-0 sm:grid-cols-2 xl:grid-cols-5">
    @foreach ([
      ['Lines', $kpi['lines']],
      ['Green', $kpi['green']],
      ['Yellow', $kpi['yellow']],
      ['Red', $kpi['red']],
      ['Failed msgs', $kpi['failed_messages']],
    ] as [$label, $value])
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ $value }}</p>
      </div>
    @endforeach
  </section>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search phone / customer" class="min-w-[180px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected($filters['tenant'] === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <select name="quality" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">Any quality</option>
      @foreach (['GREEN', 'YELLOW', 'RED', 'UNKNOWN'] as $q)
        <option value="{{ $q }}" @selected($filters['quality'] === $q)>{{ $q }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Line', 'Quality', 'Tier', 'Delivered', 'Read', 'Failed']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div>{{ $row['display_name'] ?: '—' }}</div>
            <div class="text-xs text-text-subtle">{{ $row['phone'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['quality_rating'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['messaging_limit_tier'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['delivered'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['read'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['failed'] }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No WhatsApp lines found.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
