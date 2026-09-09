<x-admin.layout title="Customer Retention - Admin" active="admin.retention.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Retention & Renewals</h1>
      <p class="text-sm text-text-subtle opacity-70">Customers nearing expiry or already expired (90-day hub).</p>
    </div>
    <a href="{{ route('admin.retention.export', request()->query()) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Export CSV</a>
  </div>

  <section class="grid gap-3 p-4 pt-0 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      ['Expiring 7d', $kpi['expiring_7']],
      ['Expiring 30d', $kpi['expiring_30']],
      ['Expiring 90d', $kpi['expiring_90']],
      ['Expired', $kpi['expired']],
      ['Suspended', $kpi['suspended']],
      ['No validity', $kpi['no_validity']],
      ['Total', $kpi['total']],
    ] as [$label, $value])
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">{{ $label }}</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ $value }}</p>
      </div>
    @endforeach
  </section>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search customer" class="min-w-[200px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <select name="window" class="rounded-lg border border-border px-3 py-2 text-sm">
      @foreach (['all' => 'All', '7' => '≤ 7 days', '30' => '≤ 30 days', '90' => '≤ 90 days', 'expired' => 'Expired', 'none' => 'No validity', 'suspended' => 'Suspended'] as $value => $label)
        <option value="{{ $value }}" @selected($filters['window'] === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="status" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">Any status</option>
      @foreach (['active', 'suspended', 'pending'] as $status)
        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Plan', 'Valid until', 'Days left', 'Status', 'Notes', 'Actions']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row['name'] }}</div>
            <div class="text-xs text-text-subtle">{{ $row['email'] ?: $row['id'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['plan'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['valid_until'] ? format_ist($row['valid_until'], 'd M Y') : '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            @if ($row['days_left'] === null)
              —
            @elseif ($row['days_left'] < 0)
              <span class="font-semibold text-red-600">{{ $row['days_left'] }}</span>
            @else
              {{ $row['days_left'] }}
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['status'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['notes_count'] }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['eye']"
              :links="['eye' => route('admin.retention.show', $row['id'])]"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No customers match these filters.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
