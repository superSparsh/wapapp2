@props(['items' => null])

@php
  $navItems = $items ?? config('team-navigation');

  $iconMap = [
    'smart-home' => 'smart-home',
    'document-text' => 'ticket',
    'data' => 'circle-square',
    'task-square' => 'box',
  ];
@endphp

<aside class="w-full shrink-0">
  <div class="overflow-hidden rounded-xl border border-divider bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
    <div class="flex items-center gap-3 border-b border-divider p-3">
      <div class="size-12 shrink-0 overflow-hidden rounded-full bg-border-light">
        <img src="{{ asset('images/avatar.png') }}" alt="" class="size-full object-cover">
      </div>
      <div class="min-w-0 flex-1">
        <p class="fd-nav-item truncate text-sm text-text-body">Info Team</p>
        <p class="fd-table-cell truncate text-sm text-text-body/60">info@tittu.in</p>
      </div>
      <span class="fd-status-chip shrink-0 rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-green-700">Active</span>
    </div>

    <nav class="flex flex-col gap-1 p-2">
      @foreach ($navItems as $item)
        @php
          $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
          $iconName = $iconMap[$item['icon']] ?? 'ticket';
        @endphp
        <a
          href="{{ route($item['route']) }}"
          @class([
            'fd-nav-item flex items-center gap-3 rounded-md px-3 py-3 transition-colors',
            'bg-green-50 text-text-subtle' => $isActive,
            'text-text-body/60 hover:bg-surface' => ! $isActive,
          ])
        >
          <x-icons.sidebar-icon :name="$iconName" :active="$isActive" class="size-5 shrink-0" />
          <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>
  </div>
</aside>
