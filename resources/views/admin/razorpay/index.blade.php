<x-admin.layout title="Razorpay Subscriptions - Admin" active="admin.razorpay.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Razorpay subscriptions</h1>
    <p class="text-sm text-text-subtle opacity-70">Tenant subscriptions linked to Razorpay.</p>
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <select name="tenant_id" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All customers</option>
      @foreach ($tenants as $tenant)
        <option value="{{ $tenant->id }}" @selected(($filters['tenant_id'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
      @endforeach
    </select>
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Customer', 'Razorpay ID', 'Status', 'Amount', 'Starts', 'Ends']" :paginator="$items">
      @forelse ($items as $row)
        <tr>
          <td class="p-3">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="font-semibold text-green-600 hover:underline">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="p-3 text-xs font-mono">{{ $row['razorpay_subscription_id'] }}</td>
          <td class="p-3 text-sm">{{ $row['status'] ?: '—' }}</td>
          <td class="p-3 text-sm">{{ $row['currency'] }} {{ $row['amount'] }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ $row['starts_at'] ?: '—' }}</td>
          <td class="p-3 text-sm text-text-subtle">{{ $row['ends_at'] ?: '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No Razorpay subscriptions found.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
