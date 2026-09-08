@php
  $defaultFeatures = ['Campaigns', 'Inbox', 'Automation', 'Webhooks'];
@endphp

<x-profile.layout title="Subscription Plans - WapApp" headerTitle="Subscription" active="profile.subscription">
  <div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Subscription Plans</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Choose a plan and pay securely with Razorpay.
      </p>
    </div>

    <nav class="flex flex-wrap items-center gap-5" aria-label="Subscription steps">
      <span class="text-xl font-semibold whitespace-nowrap text-text-primary">Select Plans</span>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0">
      <a href="{{ route('profile.subscription.billing') }}" class="text-xl font-semibold whitespace-nowrap text-blue-200 hover:text-text-primary">Billing Information</a>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0">
      <a href="{{ route('profile.subscription.payment') }}" class="text-xl font-semibold whitespace-nowrap text-blue-200 hover:text-text-primary">Payment</a>
    </nav>
  </div>

  <section class="p-4 pt-0">
    <div class="flex flex-col items-stretch gap-3 lg:flex-row lg:items-end lg:justify-center">
      @foreach ($plans as $index => $plan)
        @php
          $isCurrent = (string) $currentPlanId === (string) $plan->uuid;
          $isPopular = $index === 0;
          $period = match ($plan->billing_cycle->value) {
            'yearly' => '/365 Days',
            'quarterly' => '/90 Days',
            default => '/30 Days',
          };
          $features = collect($plan->features ?? [])->keys()->map(fn ($k) => ucfirst(str_replace('_', ' ', $k)))->whenEmpty(fn () => collect($defaultFeatures));
        @endphp

        <div class="flex min-w-0 flex-1 flex-col gap-1">
          @if ($isPopular)
            <div class="flex w-full flex-col items-center justify-center rounded-t-2xl rounded-b-lg bg-green-500 px-8 py-5">
              <p class="w-full text-center text-lg font-semibold text-white">Popular Plan</p>
            </div>
          @endif

          <div @class([
            'flex w-full flex-col gap-10 rounded-2xl border bg-elevated p-5',
            'border-[2.5px] border-green-500' => $isPopular,
            'border-border' => ! $isPopular,
          ])>
            <h2 class="text-2xl font-semibold text-text-primary">{{ $plan->name }}</h2>
            <div class="flex flex-col gap-6">
              <div>
                <p class="text-[40px] font-semibold leading-[65px] text-text-primary">₹ {{ number_format((float) $plan->price, 0) }}</p>
                <p class="text-base text-text-body">{{ $period }}</p>
              </div>

              <form method="POST" action="{{ route('profile.subscription.select-plan') }}">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->uuid }}">
                <button
                  type="submit"
                  @class([
                    'fd-btn flex w-full items-center justify-center rounded-xl p-3.5 text-base font-extrabold text-white',
                    'opacity-70' => $isCurrent,
                  ])
                  style="background: linear-gradient(178.85deg, #6dbb48 0%, rgba(17, 153, 170, 0.557) 100%);"
                  @disabled($isCurrent)
                >
                  {{ $isCurrent ? 'CURRENT PLAN' : 'BUY NOW' }}
                </button>
              </form>
            </div>

            <ul class="flex flex-col gap-4">
              @foreach ($features as $feature)
                <li class="flex items-center gap-1.5">
                  <img src="{{ asset('images/profile/plan-check.svg') }}" alt="" class="h-[20px] w-[18px] shrink-0">
                  <span class="text-sm font-medium text-text-primary">{{ $feature }}</span>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endforeach
    </div>
  </section>
</x-profile.layout>
