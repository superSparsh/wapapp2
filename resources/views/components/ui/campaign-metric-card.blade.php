@props(['title', 'count', 'total', 'percent', 'color' => 'blue', 'icon' => 'task', 'iconSrc' => null, 'detailsHref' => null, 'detailsLinkColor' => 'text-green-500'])

@php
$styles = match($color) {
    'blue' => ['bg' => 'bg-stat-blue/10', 'border' => 'border-stat-blue', 'text' => 'text-stat-blue', 'bar' => 'bg-stat-blue'],
    'green' => ['bg' => 'bg-stat-green/10', 'border' => 'border-stat-green', 'text' => 'text-stat-green', 'bar' => 'bg-stat-green'],
    'red' => ['bg' => 'bg-stat-red/10', 'border' => 'border-stat-red', 'text' => 'text-stat-red', 'bar' => 'bg-stat-red'],
    'emerald' => ['bg' => 'bg-stat-emerald/10', 'border' => 'border-stat-emerald', 'text' => 'text-stat-emerald', 'bar' => 'bg-stat-emerald'],
    'purple' => ['bg' => 'bg-stat-purple/10', 'border' => 'border-stat-purple', 'text' => 'text-stat-purple', 'bar' => 'bg-stat-purple'],
    'orange' => ['bg' => 'bg-stat-orange/10', 'border' => 'border-stat-orange', 'text' => 'text-stat-orange', 'bar' => 'bg-stat-orange'],
    default => ['bg' => 'bg-stat-blue/10', 'border' => 'border-stat-blue', 'text' => 'text-stat-blue', 'bar' => 'bg-stat-blue'],
};
$iconMap = [
    'task' => 'task',
    'send' => 'send',
    'warning' => 'warning',
    'tick-circle' => 'tick-circle',
    'task-square' => 'task-square',
    'group' => 'group',
];
$iconName = $iconMap[$icon] ?? 'task';
$barWidth = is_numeric($percent) ? $percent . '%' : $percent;
@endphp

<div {{ $attributes->class(['flex flex-col gap-3 rounded-xl border', $styles['border'], $styles['bg'], 'px-5 py-4']) }}>
  <div class="flex items-center gap-2">
    @if ($iconSrc)
      <img src="{{ $iconSrc }}" alt="" class="size-6 shrink-0" width="24" height="24">
    @else
      <x-icons.nav-icon :name="$iconName" class="size-6" />
    @endif
    <p class="text-xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">{{ $title }}</p>
  </div>
  <div class="flex items-end justify-between gap-4">
    <p class="min-w-0 leading-none" style="font-family: var(--font-display)">
      <span data-metric-count class="text-[40px] font-semibold text-text-body">{{ $count }}</span>
      <span class="text-xl text-text-muted"> of <span data-metric-total>{{ $total }}</span> messages</span>
    </p>
    <span data-metric-percent class="shrink-0 text-2xl font-medium tracking-tight {{ $styles['text'] }}" style="font-family: var(--font-display)">{{ $percent }}%</span>
  </div>
  <div class="h-2 w-full overflow-hidden rounded-full bg-border">
    <div data-metric-bar class="h-2 rounded-full {{ $styles['bar'] }}" style="width: {{ $barWidth }}"></div>
  </div>
  <div class="flex justify-end">
    @if ($detailsHref)
      <a data-metric-details href="{{ $detailsHref }}" class="fd-btn inline-flex items-center gap-1 px-4 text-base tracking-[-0.3px] {{ $detailsLinkColor }}">
        View Details
        @if ($iconSrc)
          <img src="{{ asset('images/campaigns/stats/arrow-right.svg') }}" alt="" class="size-3.5" width="14" height="14">
        @else
          <x-icons.nav-icon name="arrow-right" class="size-3.5" />
        @endif
      </a>
    @else
      <button type="button" class="fd-btn inline-flex items-center gap-1 px-4 text-base tracking-[-0.3px] {{ $detailsLinkColor }}">
        View Details
        @if ($iconSrc)
          <img src="{{ asset('images/campaigns/stats/arrow-right.svg') }}" alt="" class="size-3.5" width="14" height="14">
        @else
          <x-icons.nav-icon name="arrow-right" class="size-3.5" />
        @endif
      </button>
    @endif
  </div>
</div>
