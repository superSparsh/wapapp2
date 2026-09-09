<x-admin.layout title="Recharge Requests - Admin" active="admin.recharge-requests.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Recharge requests</h1>
    <p class="text-sm text-text-subtle opacity-70">Approving a request credits the customer wallet.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.recharge-requests.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search tenant id or notes…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All statuses</option>
          @foreach (['pending', 'approved', 'rejected'] as $option)
            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Amount', 'Status', 'Notes', 'Requested', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
            <div class="text-xs text-text-subtle">{{ $row->tenant_id }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ ucfirst($row->status) }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->notes, 60) ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->created_at) }}</td>
          <td class="w-[120px] p-2 align-middle">
            @if ($row->status === 'pending')
              <x-ui.table-actions
                :actions="['approve']"
                :links="['approve' => route('admin.recharge-requests.approve', $row)]"
                :methods="['approve' => 'POST']"
                confirm="Approve and credit the customer wallet?"
                confirm-title="Approve recharge"
                confirm-label="Approve"
              />
            @else
              <span class="block text-center text-xs text-text-subtle">{{ format_ist($row->approved_at, 'd M Y') }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No recharge requests.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
