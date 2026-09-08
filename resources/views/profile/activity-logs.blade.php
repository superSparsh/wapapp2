<x-profile.layout title="Activity Logs - WapApp" headerTitle="Activity Logs" active="profile.activity-logs">
  <div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold text-text-primary">Activity Logs</h1>
      <p class="text-sm text-text-subtle opacity-50">Account, billing, and security activity for your workspace.</p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-3">
      <label for="scope" class="text-sm font-semibold text-text-primary">Scope</label>
      <select id="scope" name="scope" class="rounded-xl border border-border bg-elevated px-3 py-2 text-sm" onchange="this.form.submit()">
        <option value="">All</option>
        @foreach ($scopes as $value => $label)
          <option value="{{ $value }}" @selected($activeScope === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <section class="p-4 pt-0">
    <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-[13px]">
          <thead>
            <tr class="bg-elevated">
              <th class="p-2 font-semibold text-text-body">When</th>
              <th class="p-2 font-semibold text-text-body">Actor</th>
              <th class="p-2 font-semibold text-text-body">Action</th>
              <th class="p-2 font-semibold text-text-body">Description</th>
              <th class="p-2 font-semibold text-text-body">IP</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($logs as $log)
              <tr class="border-t border-divider">
                <td class="p-2 whitespace-nowrap">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                <td class="p-2">{{ $log->actor_email ?? 'System' }}</td>
                <td class="p-2">
                  <span class="rounded bg-green-50 px-2 py-1 text-[10px] font-medium text-primary-2">{{ $log->scope }}</span>
                  <span class="mt-1 block text-text-subtle">{{ \App\Domains\Account\Services\ActivityLogService::labelFor((string) $log->action) }}</span>
                </td>
                <td class="p-2 text-text-body">{{ \App\Domains\Account\Services\ActivityLogService::labelFor((string) $log->action, $log->description) }}</td>
                <td class="p-2 text-text-muted">{{ $log->ip_address ?? '—' }}</td>
              </tr>
            @empty
              <tr class="border-t border-divider">
                <td colspan="5" class="p-6 text-center text-sm text-text-muted">No activity logged yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <x-ui.table-pagination :paginator="$logs" />
  </section>
</x-profile.layout>
