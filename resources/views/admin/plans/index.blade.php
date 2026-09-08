<x-admin.layout title="Plans - Admin" active="admin.plans.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Plans</h1>
      <p class="text-sm text-text-subtle opacity-70">Subscription plans available to customers.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600">Add plan</a>
  </div>

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Plan', 'Price', 'Limits', 'Customers', 'Status', 'Actions']" :paginator="$plans">
      @forelse ($plans as $plan)
        <tr>
          <td class="p-3">
            <div class="font-semibold">{{ $plan->name }}</div>
            <div class="text-xs text-text-subtle">{{ $plan->slug }}</div>
          </td>
          <td class="p-3 text-sm">{{ strtoupper($plan->currency) }} {{ $plan->price }} / {{ $plan->billing_cycle?->value }}</td>
          <td class="p-3 text-xs text-text-subtle">
            Msg {{ $plan->messages_limit ?? '∞' }} · Contacts {{ $plan->contacts_limit ?? '∞' }}
          </td>
          <td class="p-3 text-sm">{{ $plan->tenants_count }}</td>
          <td class="p-3 text-sm">{{ $plan->is_active ? 'Active' : 'Inactive' }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <a href="{{ route('admin.plans.edit', $plan) }}" class="text-xs font-semibold text-green-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.plans.toggle-status', $plan) }}">
                @csrf
                <button class="text-xs font-semibold hover:underline">{{ $plan->is_active ? 'Deactivate' : 'Activate' }}</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No plans yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
