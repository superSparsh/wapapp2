@props([
    'step' => 1,
    'campaignName' => 'New Campaign',
    'showFooter' => true,
    'cancelRoute' => 'campaigns.create.cancel',
    'showCancel' => true,
    'previousRoute' => null,
    'nextRoute' => null,
    'nextLabel' => 'Next step',
    'previousLabel' => 'Previous Step',
    'secondaryNextRoute' => null,
    'secondaryNextLabel' => null,
    'secondaryOpenModal' => null,
])

<x-layouts.app :title="$campaignName . ' - WapApp'" active="campaigns.index">
  <div class="flex min-h-full flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-4">
        <a href="{{ route($cancelRoute) }}" class="rounded-lg border border-green-100 p-2" aria-label="Back to campaigns">
          <img src="{{ asset('images/campaigns/create/arrow-left.svg') }}" alt="" class="size-6" width="24" height="24" aria-hidden="true">
        </a>
        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $campaignName }}</h1>
          <p class="text-sm leading-[1.4] text-text-subtle/50">
            Create personalized message templates for initiating conversation with your customers.
          </p>
        </div>
      </div>

      <x-campaigns.create-nav :step="$step" />
    </div>

    <div class="flex-1 px-4 pb-4">
      {{ $slot }}
    </div>

    @if ($showFooter)
      <x-campaigns.create-footer
        :cancel-route="$cancelRoute"
        :show-cancel="$showCancel"
        :previous-route="$previousRoute"
        :next-route="$nextRoute"
        :next-label="$nextLabel"
        :previous-label="$previousLabel"
        :secondary-next-route="$secondaryNextRoute"
        :secondary-next-label="$secondaryNextLabel"
        :secondary-open-modal="$secondaryOpenModal"
      />
    @endif

    {{ $modals ?? '' }}
  </div>
</x-layouts.app>
