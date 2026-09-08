<x-admin.layout title="{{ ($tenant->company_name ?: $tenant->name) }} - Admin" active="admin.customers.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.customers.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Customers</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $tenant->company_name ?: $tenant->name }}</h1>
      <p class="text-sm text-text-subtle">{{ $tenant->id }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('admin.customers.edit', $tenant) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Edit</a>
      <form method="POST" action="{{ route('admin.customers.toggle-status', $tenant) }}">
        @csrf
        <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">
          {{ $tenant->status?->value === 'active' ? 'Suspend' : 'Activate' }}
        </button>
      </form>
      <form method="POST" action="{{ route('admin.customers.login-as', $tenant) }}">
        @csrf
        <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600">Login as customer</button>
      </form>
    </div>
  </div>

  <section class="grid gap-4 p-4 pt-0 xl:grid-cols-3">
    <div class="rounded-[20px] border border-border bg-elevated p-5 xl:col-span-2">
      <h2 class="text-lg font-bold text-text-primary">Account</h2>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ([
          'Name' => $tenant->name,
          'Company' => $tenant->company_name,
          'Email' => $tenant->email,
          'Phone' => $tenant->phone,
          'Status' => $tenant->status?->value,
          'Timezone' => $tenant->timezone,
          'Plan' => $tenant->plan?->name,
          'Provisioned' => optional($tenant->provisioned_at)->toDayDateTimeString(),
          'Valid until' => data_get($settings, 'valid_until', '—'),
        ] as $label => $value)
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-text-subtle">{{ $label }}</dt>
            <dd class="mt-1 text-sm font-semibold text-text-primary">{{ $value ?: '—' }}</dd>
          </div>
        @endforeach
      </dl>
    </div>

    <div class="flex flex-col gap-4">
      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Assign plan</h2>
        <form method="POST" action="{{ route('admin.customers.assign-plan', $tenant) }}" class="mt-4 flex flex-col gap-3">
          @csrf
          <select name="plan_id" class="rounded-lg border border-border px-3 py-2 text-sm">
            <option value="">No plan</option>
            @foreach ($plans as $plan)
              <option value="{{ $plan->id }}" @selected((int) $tenant->plan_id === (int) $plan->id)>
                {{ $plan->name }} ({{ $plan->currency }} {{ $plan->price }})
              </option>
            @endforeach
          </select>
          <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Save plan</button>
        </form>
      </div>

      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Extend validity</h2>
        <form method="POST" action="{{ route('admin.customers.extend-validity', $tenant) }}" class="mt-4 flex flex-col gap-3">
          @csrf
          <input type="number" name="days" min="1" max="3650" value="30" class="rounded-lg border border-border px-3 py-2 text-sm" required>
          <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Add days</button>
        </form>
      </div>
    </div>
  </section>

  <section class="p-4 pt-0">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Login access</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-xs uppercase text-text-subtle">
              <th class="py-2">Email</th>
              <th class="py-2">Phone</th>
              <th class="py-2">Type</th>
              <th class="py-2">Active</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            @forelse ($access_rows as $row)
              <tr>
                <td class="py-2">{{ $row->email }}</td>
                <td class="py-2">{{ $row->phone ?: '—' }}</td>
                <td class="py-2">{{ $row->account_type?->value }}</td>
                <td class="py-2">{{ $row->is_active ? 'Yes' : 'No' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-4 text-text-subtle">No access rows.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
</x-admin.layout>
