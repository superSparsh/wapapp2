<x-admin.layout title="Razorpay Subscriptions - Admin" active="admin.razorpay.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Razorpay subscriptions</h1>
    <p class="text-sm text-text-subtle opacity-70">Tenant subscriptions linked to Razorpay.</p>
  </div>

  <x-admin.filter-bar
    :action="route('admin.razorpay.index')"
    :search="''"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'starts_at'"
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
    <x-ui.data-table :headers="['Customer', 'Razorpay ID', 'Status', 'Amount', 'Starts', 'Ends']" :paginator="$items">
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs font-mono">{{ $row['razorpay_subscription_id'] }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row['status']" /></td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['currency'] }} {{ $row['amount'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row['starts_at'], 'd M Y') }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row['ends_at'], 'd M Y') }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No Razorpay subscriptions found.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
