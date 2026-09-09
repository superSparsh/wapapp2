<x-admin.layout title="Customers - Admin" active="admin.customers.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Customers</h1>
      <p class="text-sm text-text-subtle opacity-70">Tenants / accounts on the platform.</p>
    </div>
  </div>

  <x-admin.filter-bar
    :action="route('admin.customers.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name, email, phone, id"
    :date-from="$filters['date_from'] ?? ''"
    :date-to="$filters['date_to'] ?? ''"
    :sort="$filters['sort'] ?? 'created_at'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All statuses</option>
          @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['Customer', 'Email / Phone', 'Plan', 'Status', 'Created', 'Actions']"
      :paginator="$customers"
    >
      @forelse ($customers as $customer)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $customer) }}" class="fd-table-name hover:text-green-500">
              {{ $customer->company_name ?: $customer->name }}
            </a>
            <div class="text-xs text-text-subtle">{{ $customer->id }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">
            <div>{{ $customer->email ?: '—' }}</div>
            <div>{{ $customer->phone ?: '—' }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $customer->plan?->name ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle">
            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium">{{ $customer->status?->value }}</span>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($customer->created_at, 'd M Y') }}</td>
          <td class="w-[160px] p-2 align-middle">
            <x-ui.table-actions
              :actions="['eye', 'edit', 'login-as']"
              :links="[
                'eye' => route('admin.customers.show', $customer),
                'edit' => route('admin.customers.edit', $customer),
                'login-as' => route('admin.customers.login-as', $customer),
              ]"
              :methods="['login-as' => 'POST']"
            />
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="p-6 text-center text-sm text-text-subtle">No customers found.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
