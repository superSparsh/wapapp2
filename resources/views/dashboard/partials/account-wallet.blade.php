@props([
    'walletBalance' => 0,
    'subscription' => [],
    'rechargeHref' => null,
])

@php
  $addCreditsHref = $rechargeHref ?? request()->fullUrlWithQuery(['modal' => 'recharge']);
  $planName = $subscription['planName'] ?? null;
  $expiresAt = $subscription['expiresAt'] ?? null;
  $daysRemaining = $subscription['daysRemaining'] ?? null;
  $validityPercent = (int) ($subscription['validityPercent'] ?? 0);
  $hasPlan = (bool) ($subscription['hasPlan'] ?? filled($planName));
  $hasSubscription = (bool) ($subscription['hasSubscription'] ?? false);
@endphp

<section class="grid gap-4 p-4 pt-0 lg:grid-cols-[1fr_400px]">
  <div class="flex flex-col gap-4 rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
    <div class="flex flex-wrap items-center gap-4">
      <p class="text-base font-bold leading-[1.5] text-danger-red" style="font-family: var(--font-display)">Account Validity</p>
      @if ($daysRemaining !== null)
        <span class="rounded bg-green-50 px-2 py-1.5 text-xs font-semibold text-green-500" style="font-family: var(--font-display)">{{ $daysRemaining }} day's remaining</span>
      @elseif ($hasPlan || $hasSubscription)
        <span class="rounded bg-green-50 px-2 py-1.5 text-xs font-semibold text-green-500" style="font-family: var(--font-display)">Active</span>
      @else
        <span class="rounded bg-stat-orange/15 px-2 py-1.5 text-xs font-semibold text-text-body" style="font-family: var(--font-display)">No active subscription</span>
      @endif
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-blue-50">
      <div class="h-full rounded-full bg-auth-gradient" style="width: {{ max($hasPlan || $hasSubscription ? 8 : 0, $validityPercent) }}%"></div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="flex items-center gap-2.5 rounded border border-border-light bg-elevated p-2">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-50">
          <img src="{{ asset('images/icons/tick-circle.svg') }}" alt="" class="size-4" width="16" height="16">
        </div>
        <p class="fd-label text-sm text-primary-2">
          @if ($planName)
            You are subscribed to {{ $planName }}
          @elseif ($hasSubscription)
            Subscription is active
          @else
            No subscription plan selected
          @endif
        </p>
      </div>
      <div class="flex items-center gap-2.5 rounded border border-border-light bg-elevated p-2">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-50">
          <img src="{{ asset('images/icons/clipboard-text.svg') }}" alt="" class="size-4" width="16" height="16">
        </div>
        <p class="fd-label text-sm text-primary-2">
          @if ($expiresAt)
            Expires on {{ $expiresAt->format('d-m-Y') }}
          @elseif ($hasPlan || $hasSubscription)
            Validity details unavailable
          @else
            Subscription expiry unavailable
          @endif
        </p>
      </div>
    </div>
    <a href="{{ route('profile.subscription') }}" class="fd-btn inline-flex items-center gap-3 text-sm font-semibold text-text-primary">
      Manage
      <x-icons.nav-icon name="arrow-right" class="size-4" />
    </a>
  </div>

  <div class="flex flex-col gap-4 rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
    <div class="flex items-start gap-3">
      <div class="min-w-0 flex-1">
        <p class="text-2xl font-bold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">₹ {{ number_format($walletBalance, 2) }}</p>
        <p class="mt-2 text-sm leading-[1.4] text-text-primary opacity-70" style="font-family: var(--font-display)">Wallet Balance</p>
      </div>
      <img src="{{ asset('images/icons/wallet.svg') }}" alt="" class="size-[62px] shrink-0 rounded-full bg-green-50 p-3" width="62" height="62">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <x-ui.link-button href="{{ route('dashboard.wallet') }}" variant="outline" size="toolbar" class="w-full justify-center gap-3 px-4 py-3">
        <x-icons.nav-icon name="refresh-2" class="size-4" />
        Wallet History
      </x-ui.link-button>
      <x-ui.link-button
        href="{{ $addCreditsHref }}"
        variant="primary"
        size="toolbar"
        class="w-full justify-center gap-3 px-4 py-3"
      >
        <x-icons.nav-icon name="refresh-2" class="size-4 brightness-0 invert" />
        Add Credits
      </x-ui.link-button>
    </div>
  </div>
</section>
