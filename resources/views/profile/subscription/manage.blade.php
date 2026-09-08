@php
  $plan = $summary['plan'] ?? null;
  $subscription = $summary['subscription'] ?? null;
@endphp

<x-profile.layout title="Manage Subscription - WapApp" headerTitle="Subscription" active="profile.subscription">
  <div class="p-4 pb-0">
    <h1 class="text-2xl font-bold text-text-primary">Manage Subscription</h1>
    <p class="text-sm text-text-subtle">Current plan and renewal settings.</p>
  </div>

  <section class="p-4 pt-0">
    <div class="max-w-2xl rounded-xl border border-divider bg-elevated p-5 shadow-[0px_4px_12px_rgba(0,0,0,0.04)]">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="text-lg font-semibold text-text-primary">{{ $plan?->name ?? 'No plan' }}</h3>
          <p class="mt-1 text-2xl text-green-500">{{ $plan ? '₹ '.number_format((float) $plan->price, 2) : '—' }}</p>
          @if ($subscription?->ends_at)
            <p class="mt-2 text-sm text-text-body/60">Expires on {{ $subscription->ends_at->format('d M Y') }}</p>
          @endif
        </div>
        <span class="inline-flex rounded bg-green-50 px-2 py-1 text-xs font-medium text-primary-2">
          {{ $subscription?->status?->value ?? 'none' }}
        </span>
      </div>

      <div class="mt-6 flex flex-wrap gap-3 border-t border-divider pt-6">
        <a href="{{ route('profile.subscription.upgrade') }}" class="fd-btn-sm inline-flex rounded bg-green-500 px-6 py-3 text-primary-2">Upgrade Plan</a>
        <a href="{{ route('profile.subscription.billing') }}" class="fd-btn-sm inline-flex rounded border border-border-light bg-elevated px-6 py-3 text-primary-2">View Billing</a>
        @if ($subscription && $subscription->status->value === 'active')
          <form method="POST" action="{{ route('profile.subscription.cancel') }}" data-confirm="Cancel your subscription? This cannot be undone." data-confirm-title="Cancel subscription" data-confirm-label="Yes, cancel" data-confirm-variant="danger">
            @csrf
            <button type="submit" class="fd-btn-sm inline-flex rounded border border-border-light bg-elevated px-6 py-3 text-[red]">Cancel Subscription</button>
          </form>
        @endif
      </div>
    </div>
  </section>
</x-profile.layout>
