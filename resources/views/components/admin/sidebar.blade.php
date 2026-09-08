@props(['mobile' => false, 'active' => ''])

@php
    $navItems = config('admin-navigation');
    $activeNav = $active ?? '';

    $childRouteActive = function (array $child) use ($activeNav): bool {
        if ($activeNav !== '') {
            $keys = array_filter(array_merge(
                [$child['active_key'] ?? null, $child['route'] ?? null],
                $child['matches'] ?? [],
            ));
            if (in_array($activeNav, $keys, true)) {
                return true;
            }
        }

        $routes = array_merge([$child['route']], $child['matches'] ?? []);

        return collect($routes)->contains(
            fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*')
        );
    };

    $itemRouteActive = function (array $item) use ($activeNav, $childRouteActive): bool {
        if ($activeNav !== '' && ($activeNav === ($item['route'] ?? '') || in_array($activeNav, $item['matches'] ?? [], true))) {
            return true;
        }

        if (! empty($item['children']) && collect($item['children'])->contains(fn ($child) => $childRouteActive($child))) {
            return true;
        }

        $routes = array_merge([$item['route']], $item['matches'] ?? []);

        return collect($routes)->contains(
            fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*')
        );
    };
@endphp

<aside class="relative flex h-full w-[243px] shrink-0 flex-col border-r border-border-sidebar bg-elevated">
  <div class="flex h-[84px] shrink-0 items-center px-6">
    <a href="{{ route('admin.dashboard') }}" class="flex w-full items-center gap-3 transition-opacity hover:opacity-90">
      <img src="{{ asset('images/logo.png') }}" alt="WapApp" class="size-9 shrink-0 rounded-lg object-contain" width="36" height="36">
      <span class="fd-logo min-w-0 text-xl font-bold tracking-tight text-text-primary whitespace-nowrap">
        Admin
      </span>
    </a>
  </div>

  <nav class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-4">
    @foreach ($navItems as $item)
      @php
        $hasChildren = ! empty($item['children'] ?? []);
        $isParentActive = $itemRouteActive($item);
        $isExpanded = $hasChildren && $isParentActive;
      @endphp

      @if ($hasChildren)
        <div class="flex w-full flex-col items-end gap-1">
          <a
            href="{{ route($item['route']) }}"
            @class([
              'fd-nav-item flex w-full items-center gap-3 rounded-md p-3 transition-colors',
              'border-l-4 border-text-subtle bg-green-500 text-text-subtle' => $isParentActive,
              'text-blue-200 hover:bg-surface' => ! $isParentActive,
            ])
          >
            <x-icons.sidebar-icon :name="$item['icon']" :active="$isParentActive" class="size-5 shrink-0" />
            <span class="min-w-0 flex-1 whitespace-nowrap">{{ $item['label'] }}</span>
            <img
              src="{{ asset('images/icons/sidebar/menu/chevron-right.svg') }}"
              alt=""
              @class(['size-4 shrink-0 transition-transform', 'rotate-90' => $isExpanded])
            >
          </a>
          @if ($isExpanded)
            <div class="flex w-[calc(100%-12px)] flex-col gap-1 border-l border-border-sidebar pl-2">
              @foreach ($item['children'] as $child)
                @php $childActive = $childRouteActive($child); @endphp
                <a
                  href="{{ route($child['route']) }}"
                  @class([
                    'rounded-md px-3 py-2 text-sm transition-colors',
                    'bg-surface font-semibold text-text-primary' => $childActive,
                    'text-text-subtle hover:bg-surface' => ! $childActive,
                  ])
                >
                  {{ $child['label'] }}
                </a>
              @endforeach
            </div>
          @endif
        </div>
      @else
        <a
          href="{{ route($item['route']) }}"
          @class([
            'fd-nav-item flex w-full items-center gap-3 rounded-md p-3 transition-colors',
            'border-l-4 border-text-subtle bg-green-500 text-text-subtle' => $isParentActive,
            'text-blue-200 hover:bg-surface' => ! $isParentActive,
          ])
        >
          <x-icons.sidebar-icon :name="$item['icon']" :active="$isParentActive" class="size-5 shrink-0" />
          <span class="min-w-0 flex-1 whitespace-nowrap">{{ $item['label'] }}</span>
        </a>
      @endif
    @endforeach
  </nav>
</aside>
