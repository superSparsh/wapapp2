<x-admin.layout title="Feature requests - Admin" active="admin.announcements.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-green-500 hover:underline">← Announcements</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">Requests — {{ $announcement->title }}</h1>
      <p class="text-sm text-text-subtle opacity-70">Customers who requested activation / demo for this feature.</p>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif

  <x-admin.filter-bar
    :action="route('admin.announcements.requests', $announcement)"
    :search="''"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Tenant', 'Plan', 'Status', 'Requested', 'Actions']" :paginator="$requests">
      @forelse ($requests as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->customer_name ?: '—' }}</div>
            <div class="text-xs text-text-subtle">{{ $row->customer_email }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs">{{ $row->tenant_id }}</td>
          <td class="fd-table-cell p-2 align-middle">{{ $row->plan_name ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle">
            @if ($row->is_acknowledged)
              <span class="rounded bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-600">Acknowledged</span>
            @else
              <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs">{{ format_ist($row->created_at, 'd M Y h:i A') }}</td>
          <td class="w-[180px] p-2 align-middle">
            <div class="flex items-center gap-2">
              @unless ($row->is_acknowledged)
                <form method="POST" action="{{ route('admin.announcements.requests.acknowledge', $row) }}">
                  @csrf
                  <button type="submit" class="rounded border border-green-500 px-2 py-1 text-xs font-semibold text-green-500">Acknowledge</button>
                </form>
              @endunless
              <form method="POST" action="{{ route('admin.announcements.requests.destroy', $row) }}" data-confirm="Delete this request?" data-confirm-title="Delete request" data-confirm-label="Delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded border border-red-500 px-2 py-1 text-xs font-semibold text-red-500">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No requests yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
