<x-admin.layout title="Activity logs — {{ $tenant->company_name ?: $tenant->name }}" active="admin.customers.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.customers.show', $tenant) }}" class="text-xs font-semibold text-green-600 hover:underline">← {{ $tenant->company_name ?: $tenant->name }}</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">Activity logs</h1>
      <p class="text-sm text-text-subtle">Account, billing, and security activity for this customer.</p>
    </div>
  </div>

  <x-admin.filter-bar
    :action="route('admin.customers.activity-logs', $tenant)"
    :search="''"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'created_at'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Scope</span>
        <select name="scope" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All</option>
          @foreach ($scopes as $value => $label)
            <option value="{{ $value }}" @selected(($filters['scope'] ?? $activeScope ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['When', 'Actor', 'Action', 'Description', 'IP']"
      :paginator="$logs"
    >
      @forelse ($logs as $log)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle text-sm whitespace-nowrap">{{ format_ist($log->created_at) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $log->actor_email ?? 'System' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <span class="rounded bg-surface px-2 py-0.5 text-[10px] font-medium">{{ $log->scope }}</span>
            <div class="mt-1 text-text-subtle">{{ \App\Domains\Account\Services\ActivityLogService::labelFor((string) $log->action) }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ \App\Domains\Account\Services\ActivityLogService::labelFor((string) $log->action, $log->description) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ $log->ip_address ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="p-6 text-center text-sm text-text-subtle">No activity logged yet.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
