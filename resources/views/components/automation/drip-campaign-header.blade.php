@props([
    'campaign',
    'activeTab' => 'settings',
])

@php
$tabs = [
    'settings' => ['label' => 'Settings', 'icon' => 'settings-bulk.svg', 'route' => 'automation.drip.design'],
    'audience' => ['label' => 'Audience', 'icon' => 'messages.svg', 'route' => 'automation.drip.audience'],
    'insights' => ['label' => 'Insights', 'icon' => 'bubble-bulk.svg', 'route' => 'automation.drip.insights'],
    'statistics' => ['label' => 'Statistics', 'icon' => 'chart-bulk-tab.svg', 'route' => 'automation.drip.statistics'],
];
$triggerConfirmOpen = request('modal') === 'trigger-confirm';
$description = sprintf(
    'Automated drip campaign "%s"%s',
    $campaign->name,
    $campaign->audience ? ', targeting audience "' . $campaign->audience->name . '"' : '',
);
@endphp

<div class="flex flex-col gap-4 p-4">
  <div class="flex flex-col gap-1">
    <div class="flex items-start gap-1">
      <h1 class="min-w-0 flex-1 text-xl font-bold leading-[1.5] text-text-primary">{{ $campaign->name }}</h1>
      <form method="POST" action="{{ route('automation.drip.toggle', $campaign) }}" data-drip-toggle class="shrink-0 p-2">
        @csrf
        @method('PATCH')
        <x-ui.toggle-switch
          :active="$campaign->isActive()"
          :submit="true"
          aria-label="Toggle campaign"
        />
      </form>
    </div>
    <p class="w-full text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      {{ $description }}
    </p>
  </div>

  <div class="flex w-full items-center rounded-lg bg-green-100 p-1" data-drip-campaign-tabs role="tablist">
    @foreach ($tabs as $key => $tab)
      <a
        href="{{ route($tab['route'], $campaign) }}"
        role="tab"
        aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
        @class([
          'flex flex-1 items-center justify-center gap-1 rounded-lg p-2 text-center text-[13px] font-medium leading-[1.5] whitespace-nowrap transition-colors sm:text-sm',
          'bg-green-500 text-primary-2 shadow-sm' => $activeTab === $key,
          'text-text-body hover:bg-green-50' => $activeTab !== $key,
        ])
      >
        <img src="{{ asset('images/automation/' . $tab['icon']) }}" alt="" class="size-5 shrink-0" width="20" height="20">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  {{ $slot }}
</div>

<x-automation.trigger-confirm-modal
  :open="$triggerConfirmOpen"
  :close-href="$triggerConfirmOpen ? url()->current() : null"
  :campaign="$campaign"
/>
