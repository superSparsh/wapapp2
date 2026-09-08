<x-admin.layout title="Zoho / Razorpay Credits - Admin" active="admin.zoho-credits.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Zoho / Razorpay credits</h1>
    <p class="text-sm text-text-subtle opacity-70">Wallet credit requests received from billing systems.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <select name="source" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All sources</option>
      @foreach ($sources as $option)
        <option value="{{ $option }}" @selected($source === $option)>{{ ucfirst($option) }}</option>
      @endforeach
    </select>
    <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected($tenantId === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Source', 'Amount', 'Status', 'Credited', '']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
            <div class="text-xs text-text-subtle">{{ $row->invoice_number ?: $row->external_id ?: '—' }}</div>
          </td>
          <td class="p-3 text-sm">{{ ucfirst($row->source) }}</td>
          <td class="p-3 text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
          <td class="p-3 text-sm">{{ ucfirst($row->status) }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ optional($row->wallet_credited_at)->toDayDateTimeString() ?: '—' }}</td>
          <td class="p-3">
            <a href="{{ route('admin.zoho-credits.show', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">View</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No credit requests.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
