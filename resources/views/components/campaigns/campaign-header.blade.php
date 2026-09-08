@props([
    'campaign',
    'activeTab' => 'overview',
])

@php
$tabs = [
    'overview' => ['label' => 'Overview', 'icon' => 'settings-bulk.svg', 'route' => route('campaigns.show', $campaign)],
    'statistics' => ['label' => 'Statistics', 'icon' => 'chart-bulk-tab.svg', 'route' => route('campaigns.statistics', $campaign)],
    'recipients' => ['label' => 'Recipients', 'icon' => 'messages.svg', 'route' => route('campaigns.statistics.detail', $campaign)],
];
$statusLabel = $campaign->status?->label() ?? 'Unknown';
$statusColor = match ($campaign->status) {
    \App\Enums\CampaignStatus::Sending => 'text-teal-700 bg-teal-50',
    \App\Enums\CampaignStatus::Scheduled => 'text-blue-500 bg-[rgba(0,0,255,0.1)]',
    \App\Enums\CampaignStatus::Completed => 'text-green-700 bg-green-50',
    \App\Enums\CampaignStatus::Paused => 'text-orange-500 bg-[rgba(255,165,0,0.1)]',
    \App\Enums\CampaignStatus::Cancelled => 'text-red-500 bg-[rgba(255,0,0,0.1)]',
    default => 'text-text-muted bg-[rgba(0,0,0,0.05)]',
};
@endphp

<div class="flex flex-col gap-4 p-4">
  <div class="flex flex-col gap-1">
    <div class="flex items-start gap-3">
      <h1 class="min-w-0 flex-1 text-xl font-bold leading-[1.5] text-text-primary">{{ $campaign->name }}</h1>
      <span class="shrink-0 inline-flex items-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap {{ $statusColor }}">
        {{ $statusLabel }}
      </span>
      @if ($campaign->isSending() || $campaign->isPaused())
        <form method="POST" action="{{ route('campaigns.toggle', $campaign) }}" data-campaign-toggle class="shrink-0">
          @csrf
          @method('PATCH')
          <button type="submit" class="cursor-pointer" aria-label="Toggle campaign">
            <x-ui.toggle-switch
              :active="$campaign->isSending()"
              aria-label="Toggle campaign"
            />
          </button>
        </form>
      @endif
    </div>
    <p class="w-full text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Campaign "{{ $campaign->name }}"{{ $campaign->audience ? ', targeting audience "' . $campaign->audience->name . '"' : '' }}
    </p>
  </div>

  <nav class="flex w-full items-center gap-1 rounded-xl bg-muted-surface p-1" aria-label="Campaign sections">
    @foreach ($tabs as $key => $tab)
      <a
        href="{{ $tab['route'] }}"
        @class([
          'flex flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-center text-[13px] font-semibold leading-[1.5] whitespace-nowrap transition-all duration-200 sm:text-sm',
          'bg-green-500 text-white shadow-sm' => $activeTab === $key,
          'text-text-body hover:bg-elevated hover:text-text-primary' => $activeTab !== $key,
        ])
        aria-current="{{ $activeTab === $key ? 'page' : 'false' }}"
      >
        <img
          src="{{ asset('images/automation/' . $tab['icon']) }}"
          alt=""
          @class([
            'size-5 shrink-0',
            'brightness-0 invert' => $activeTab === $key,
          ])
          width="20"
          height="20"
        >
        {{ $tab['label'] }}
      </a>
    @endforeach
  </nav>

  {{ $slot }}
</div>
