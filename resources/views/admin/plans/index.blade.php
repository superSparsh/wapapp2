<x-admin.layout title="Plans - Admin" active="admin.plans.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Plans</h1>
      <p class="text-sm text-text-subtle opacity-70">Subscription plans available to customers.</p>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600">Add plan</a>
  </div>

  <x-admin.filter-bar
    :action="route('admin.plans.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search plan name or slug…"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'sort_order'"
    :direction="$filters['direction'] ?? 'asc'"
    :sort-options="$sortOptions"
  />

  <div class="p-4 pt-0">
    <x-ui.data-table :headers="['Plan', 'Price / Wallet', 'Validity', 'Features', 'Customers', 'Status', 'Actions']" :paginator="$plans">
      @forelse ($plans as $plan)
        @php
          $featureCount = \App\Domains\Billing\Support\PlanFeatureCatalog::enabledCount(is_array($plan->features) ? $plan->features : []);
          $isAdvance = ! empty(data_get($plan->features, 'advance')) || ! empty(data_get($plan->features, 'carousel_templates'));
        @endphp
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $plan->name }}</div>
            <div class="text-xs text-text-subtle">{{ $plan->slug }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div>{{ strtoupper($plan->currency) }} {{ $plan->price }} / {{ $plan->billing_cycle?->value }}</div>
            <div class="text-xs text-text-subtle">Wallet start: {{ strtoupper($plan->currency) }} {{ number_format((float) $plan->starting_wallet_balance, 2) }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $plan->resolvedValidityDays() }} days</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">
            <div>{{ $featureCount }} modules</div>
            <div>{{ $isAdvance ? 'Advance' : 'Basic' }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $plan->tenants_count }}</td>
          <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$plan->is_active" :label="$plan->is_active ? 'Active' : 'Inactive'" /></td>
          <td class="w-[220px] p-2 align-middle">
            <div class="flex flex-wrap items-center gap-2">
              <a href="{{ route('admin.plans.edit', $plan) }}" class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold hover:bg-surface">Edit</a>
              <form method="POST" action="{{ route('admin.plans.toggle-status', $plan) }}" class="inline">
                @csrf
                <button
                  type="submit"
                  class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold {{ $plan->is_active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}"
                >
                  {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No plans yet.</td></tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
