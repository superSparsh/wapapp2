<x-admin.layout title="Wallet Recharges - Admin" active="admin.wallet-recharges.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Wallet recharges</h1>
    <p class="text-sm text-text-subtle opacity-70">Credit transactions across customer wallets.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.wallet-recharges.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search description / payment id"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'created_at'"
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
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Amount', 'Description', 'Reference', 'When']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['currency'] }} {{ $row['amount'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['description'] ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $row['razorpay_payment_id'] ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row['created_at']) }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No wallet credits found.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
