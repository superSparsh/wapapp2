<x-admin.layout title="Customer Retention - Admin" active="admin.retention.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Retention & Renewals</h1>
      <p class="text-sm text-text-subtle opacity-70">Customers nearing expiry or already expired (90-day hub).</p>
    </div>
    <a href="{{ route('admin.retention.export', request()->query()) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Export CSV</a>
  </div>

  @php
    $activeWindow = (string) ($filters['window'] ?? '90');
    $kpiCards = [
      ['label' => 'Expiring 7d', 'value' => $kpi['expiring_7'], 'window' => '7'],
      ['label' => 'Expiring 30d', 'value' => $kpi['expiring_30'], 'window' => '30'],
      ['label' => 'Expiring 90d', 'value' => $kpi['expiring_90'], 'window' => '90'],
      ['label' => 'Expired', 'value' => $kpi['expired'], 'window' => 'expired'],
      ['label' => 'Suspended', 'value' => $kpi['suspended'], 'window' => 'suspended'],
      ['label' => 'No validity', 'value' => $kpi['no_validity'], 'window' => 'none'],
      ['label' => 'Total', 'value' => $kpi['total'], 'window' => 'all'],
    ];
    $kpiQueryBase = request()->except(['page', 'window']);
  @endphp
  <section class="grid gap-3 p-4 pt-0 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($kpiCards as $card)
      @php $isActive = $activeWindow === $card['window']; @endphp
      <a
        href="{{ route('admin.retention.index', array_merge($kpiQueryBase, ['window' => $card['window']])) }}"
        class="rounded-[20px] border p-4 transition-colors hover:border-green-500 hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-500 {{ $isActive ? 'border-green-500 bg-green-50 ring-1 ring-green-500' : 'border-border bg-elevated' }}"
        aria-current="{{ $isActive ? 'true' : 'false' }}"
      >
        <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">{{ $card['label'] }}</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ $card['value'] }}</p>
        <p class="mt-1 text-[11px] font-medium {{ $isActive ? 'text-green-700' : 'text-text-subtle' }}">
          {{ $isActive ? 'Showing below' : 'Click to filter table' }}
        </p>
      </a>
    @endforeach
  </section>

  <x-admin.filter-bar
    :action="route('admin.retention.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search customer"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'name'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Window</span>
        <select name="window" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          @foreach (['all' => 'All', '7' => '≤ 7 days', '30' => '≤ 30 days', '90' => '≤ 90 days', 'expired' => 'Expired', 'none' => 'No validity', 'suspended' => 'Suspended'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['window'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">Any status</option>
          @foreach (['active', 'suspended', 'pending'] as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Plan', 'Valid until', 'Days left', 'Status', 'Notes', 'Actions']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row['name'] }}</div>
            <div class="text-xs text-text-subtle">{{ $row['email'] ?: $row['id'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['plan'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['valid_until'] ? format_ist($row['valid_until'], 'd M Y') : '-' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            @if ($row['days_left'] === null)
              -
            @elseif ($row['days_left'] < 0)
              <span class="font-semibold text-red-600">{{ $row['days_left'] }}</span>
            @else
              {{ $row['days_left'] }}
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row['status']" /></td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['notes_count'] }}</td>
          <td class="w-[220px] p-2 align-middle">
            <div class="flex flex-wrap items-center gap-1.5">
              <x-ui.table-actions
                :actions="['eye']"
                :links="['eye' => route('admin.retention.show', $row['id'])]"
              />
              <form method="POST" action="{{ route('admin.retention.whatsapp', $row['id']) }}">
                @csrf
                <button type="submit" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold hover:bg-surface" title="Send WhatsApp to customer + admin">WA</button>
              </form>
              <form method="POST" action="{{ route('admin.retention.email', $row['id']) }}">
                @csrf
                <button type="submit" class="rounded-md border border-border px-2 py-1 text-[11px] font-semibold hover:bg-surface" title="Send email to customer + admin">Email</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No customers match these filters.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
