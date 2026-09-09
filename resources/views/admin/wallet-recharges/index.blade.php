<x-admin.layout title="Wallet Recharges - Admin" active="admin.wallet-recharges.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Wallet recharges</h1>
    <p class="text-sm text-text-subtle opacity-70">Credit transactions across customer wallets.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search description / payment id" class="min-w-[200px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected($filters['tenant'] === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

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
