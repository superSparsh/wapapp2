<x-admin.layout title="Zoho / Razorpay Credits - Admin" active="admin.zoho-credits.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Zoho / Razorpay credits</h1>
    <p class="text-sm text-text-subtle opacity-70">Wallet credit requests received from billing systems.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.zoho-credits.index')"
    :search="''"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Source</span>
        <select name="source" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All sources</option>
          @foreach ($sources as $option)
            <option value="{{ $option }}" @selected($source === $option)>{{ ucfirst($option) }}</option>
          @endforeach
        </select>
      </label>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Customer</span>
        <select name="tenant" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All customers</option>
          @foreach ($tenants as $tenant)
            <option value="{{ $tenant->id }}" @selected($tenantId === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Source', 'Amount', 'Status', 'Credited', 'Actions']" :paginator="$rows">
      @forelse ($rows as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
            <div class="text-xs text-text-subtle">{{ $row->invoice_number ?: $row->external_id ?: '—' }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ ucfirst($row->source) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row->status" /></td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->wallet_credited_at) }}</td>
          <td class="w-[120px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['eye']"
              :links="['eye' => route('admin.zoho-credits.show', $row)]"
            />
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No credit requests.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
