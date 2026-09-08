<x-admin.layout title="Billing Audit - Admin" active="admin.billing-audit.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Billing audit</h1>
      <p class="text-sm text-text-subtle opacity-70">Wallet credits, Razorpay orders, and subscriptions.</p>
    </div>
    <a href="{{ route('admin.billing-audit.export', request()->query()) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Export CSV</a>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search reference" class="min-w-[180px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <select name="type" class="rounded-lg border border-border px-3 py-2 text-sm">
      @foreach (['all' => 'All', 'wallet' => 'Wallet', 'razorpay' => 'Razorpay', 'subscriptions' => 'Subscriptions'] as $value => $label)
        <option value="{{ $value }}" @selected(($filters['type'] ?: 'all') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected($filters['tenant'] === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Source', 'Amount', 'Description', 'Reference', 'When']" :paginator="$items">
      @forelse ($items as $row)
        <tr>
          <td class="p-3">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="font-semibold text-green-600 hover:underline">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="p-3 text-xs font-medium uppercase text-text-subtle">{{ $row['source'] }}</td>
          <td class="p-3 text-sm font-semibold">{{ $row['currency'] }} {{ $row['amount'] }}</td>
          <td class="p-3 text-sm">{{ $row['description'] }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ $row['razorpay_payment_id'] ?: '—' }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ $row['created_at'] ?: '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No billing events found.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
