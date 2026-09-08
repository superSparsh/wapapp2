<x-admin.layout title="Customers - Admin" active="admin.customers.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Customers</h1>
      <p class="text-sm text-text-subtle opacity-70">Tenants / accounts on the platform.</p>
    </div>
  </div>

  <form method="GET" action="{{ route('admin.customers.index') }}" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input
      type="search"
      name="q"
      value="{{ $filters['q'] }}"
      placeholder="Search name, email, phone, id"
      class="min-w-[220px] flex-1 rounded-lg border border-border px-3 py-2 text-sm"
    >
    <select name="status" class="rounded-lg border border-border px-3 py-2 text-sm">
      <option value="">All statuses</option>
      @foreach ($statuses as $status)
        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ ucfirst($status->value) }}</option>
      @endforeach
    </select>
    <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">Filter</button>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['Customer', 'Email / Phone', 'Plan', 'Status', 'Created', 'Actions']"
      :paginator="$customers"
    >
      @forelse ($customers as $customer)
        <tr class="bg-elevated">
          <td class="p-3">
            <a href="{{ route('admin.customers.show', $customer) }}" class="font-semibold text-text-primary hover:underline">
              {{ $customer->company_name ?: $customer->name }}
            </a>
            <div class="text-xs text-text-subtle">{{ $customer->id }}</div>
          </td>
          <td class="p-3 text-sm text-text-subtle">
            <div>{{ $customer->email ?: '—' }}</div>
            <div>{{ $customer->phone ?: '—' }}</div>
          </td>
          <td class="p-3 text-sm">{{ $customer->plan?->name ?: '—' }}</td>
          <td class="p-3">
            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium">{{ $customer->status?->value }}</span>
          </td>
          <td class="p-3 text-sm text-text-subtle">{{ optional($customer->created_at)->format('d M Y') }}</td>
          <td class="p-3">
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-semibold text-green-600 hover:underline">View</a>
              <a href="{{ route('admin.customers.edit', $customer) }}" class="text-xs font-semibold text-text-primary hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.customers.login-as', $customer) }}" class="inline">
                @csrf
                <button type="submit" class="text-xs font-semibold text-amber-700 hover:underline">Login as</button>
              </form>
            </div>
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
