<x-admin.layout title="Recharge Requests - Admin" active="admin.recharge-requests.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Recharge requests</h1>
    <p class="text-sm text-text-subtle opacity-70">Approving a request credits the customer wallet.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <select name="status" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All statuses</option>
      @foreach (['pending', 'approved', 'rejected'] as $option)
        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Amount', 'Status', 'Notes', 'Requested', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
            <div class="text-xs text-text-subtle">{{ $row->tenant_id }}</div>
          </td>
          <td class="p-3 text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
          <td class="p-3 text-sm">{{ ucfirst($row->status) }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->notes, 60) ?: '—' }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ optional($row->created_at)->diffForHumans() ?: '—' }}</td>
          <td class="p-3">
            @if ($row->status === 'pending')
              <form method="POST" action="{{ route('admin.recharge-requests.approve', $row) }}" data-confirm="Approve and credit the customer wallet?" data-confirm-title="Approve recharge">
                @csrf
                <button class="text-xs font-semibold text-green-600 hover:underline">Approve</button>
              </form>
            @else
              <span class="text-xs text-text-subtle">{{ optional($row->approved_at)->toDateString() ?: '—' }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No recharge requests.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
