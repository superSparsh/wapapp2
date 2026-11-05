<x-admin.layout title="Recent Campaigns - Admin" active="admin.recent-campaigns.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Recent campaigns</h1>
    <p class="text-sm text-text-subtle opacity-70">See which customers sent campaigns recently, with delivery stats across tenants.</p>
  </div>

  <div class="mx-4 mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Shown</div>
      <div class="mt-1 text-2xl font-bold">{{ $kpi['total'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Sending</div>
      <div class="mt-1 text-2xl font-bold text-amber-600">{{ $kpi['sending'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Completed</div>
      <div class="mt-1 text-2xl font-bold text-green-600">{{ $kpi['completed'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Failed</div>
      <div class="mt-1 text-2xl font-bold text-red-600">{{ $kpi['failed'] ?? 0 }}</div>
    </div>
  </div>

  <x-admin.filter-bar
    :action="route('admin.recent-campaigns.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search customer / campaign / template / phone"
    :show-dates="true"
    :date-from="$filters['date_from'] ?? ''"
    :date-to="$filters['date_to'] ?? ''"
    :sort="$filters['sort'] ?? 'activity_at'"
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
      <label class="flex min-w-[140px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All statuses</option>
          @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['Customer', 'Campaign', 'Status', 'Template / Line', 'Recipients', 'Delivered', 'Failed', 'Read', 'Activity']"
      :paginator="$items"
    >
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">
              {{ $row['tenant_name'] }}
            </a>
            <div class="text-xs text-text-subtle">by {{ $row['created_by'] ?: '-' }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row['name'] }}</div>
            <div class="text-xs text-text-subtle">{{ $row['audience'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <x-admin.status-badge :status="$row['status']" />
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div>{{ $row['template_name'] }}</div>
            <div class="text-xs text-text-subtle">
              {{ $row['line_name'] ? $row['line_name'].' · ' : '' }}{{ $row['line_phone'] }}
            </div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ number_format($row['total_recipients']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-green-700">{{ number_format($row['total_delivered']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-red-700">{{ number_format($row['total_failed']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ number_format($row['total_read']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">
            @if (! empty($row['activity_at']))
              {{ format_ist(\Illuminate\Support\Carbon::parse($row['activity_at'])) }}
            @else
              -
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="p-6 text-center text-sm text-text-subtle">No recent campaigns found.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
