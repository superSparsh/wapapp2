<x-admin.layout title="Admin Dashboard - WapApp" active="admin.dashboard">
  <div class="flex flex-col gap-1 p-4">
    <h1 class="text-2xl font-bold text-text-primary">Dashboard</h1>
    <p class="text-sm text-text-subtle opacity-70">Platform overview for customers, plans, and admins.</p>
  </div>

  <section class="grid gap-4 p-4 pt-0 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      ['label' => 'Customers', 'value' => $stats['customers_total'], 'hint' => $stats['customers_active'].' active'],
      ['label' => 'Suspended', 'value' => $stats['customers_suspended'], 'hint' => 'Need review'],
      ['label' => 'Active plans', 'value' => $stats['plans_active'], 'hint' => 'Sellable plans'],
      ['label' => 'Admins', 'value' => $stats['admins_active'], 'hint' => 'Active staff'],
    ] as $card)
      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <p class="text-sm font-medium text-text-subtle">{{ $card['label'] }}</p>
        <p class="mt-2 text-3xl font-bold text-text-primary">{{ $card['value'] }}</p>
        <p class="mt-1 text-xs text-text-subtle">{{ $card['hint'] }}</p>
      </div>
    @endforeach
  </section>

  <section class="grid gap-4 p-4 pt-0 xl:grid-cols-2">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary">Recent customers</h2>
        <a href="{{ route('admin.customers.index') }}" class="text-sm font-semibold text-green-600 hover:underline">View all</a>
      </div>
      <div class="divide-y divide-border">
        @forelse ($stats['recent_customers'] as $customer)
          <a href="{{ route('admin.customers.show', $customer) }}" class="flex items-center justify-between py-3 hover:opacity-80">
            <div>
              <p class="text-sm font-semibold text-text-primary">{{ $customer->company_name ?: $customer->name }}</p>
              <p class="text-xs text-text-subtle">{{ $customer->email ?: $customer->id }}</p>
            </div>
            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium text-text-subtle">{{ $customer->status?->value }}</span>
          </a>
        @empty
          <p class="py-6 text-sm text-text-subtle">No customers yet.</p>
        @endforelse
      </div>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-text-primary">Plan distribution</h2>
        <a href="{{ route('admin.plans.index') }}" class="text-sm font-semibold text-green-600 hover:underline">Manage plans</a>
      </div>
      <div class="divide-y divide-border">
        @forelse ($stats['plan_distribution'] as $plan)
          <div class="flex items-center justify-between py-3">
            <p class="text-sm font-semibold text-text-primary">{{ $plan->name }}</p>
            <p class="text-sm text-text-subtle">{{ $plan->tenants_count }} customers</p>
          </div>
        @empty
          <p class="py-6 text-sm text-text-subtle">No plans configured.</p>
        @endforelse
      </div>
    </div>
  </section>

  <section class="grid gap-3 p-4 pt-0 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['Customers', route('admin.customers.index')],
      ['WhatsApp Health', route('admin.whatsapp-health.index')],
      ['Billing audit', route('admin.billing-audit.index')],
      ['Settings', route('admin.settings.index')],
    ] as [$label, $href])
      <a href="{{ $href }}" class="rounded-xl border border-border bg-elevated px-4 py-3 text-sm font-semibold text-text-primary hover:bg-surface">
        {{ $label }} →
      </a>
    @endforeach
  </section>
</x-admin.layout>
