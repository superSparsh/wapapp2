@props(['mobile' => false])

@php
    $navItems = $navItems ?? config('navigation');
    $activeNav = $active ?? '';

    $childRouteActive = function (array $child) use ($activeNav): bool {
        if ($activeNav !== '') {
            $activeKeys = array_filter(array_merge(
                [$child['active_key'] ?? null, $child['route'] ?? null],
                $child['matches'] ?? [],
            ));

            if (in_array($activeNav, $activeKeys, true)) {
                return true;
            }
        }

        $routes = array_merge([$child['route']], $child['matches'] ?? []);

        return collect($routes)->contains(
            fn (string $route) => request()->routeIs($route) || request()->routeIs($route . '.*')
        );
    };

    $itemRouteActive = function (array $item) use ($activeNav): bool {
        if ($activeNav !== '' && ($activeNav === ($item['route'] ?? '') || in_array($activeNav, $item['matches'] ?? [], true))) {
            return true;
        }

        $routes = array_merge([$item['route']], $item['matches'] ?? []);

        return collect($routes)->contains(
            fn (string $route) => request()->routeIs($route) || request()->routeIs($route . '.*')
        );
    };
    $collapsed = false;
@endphp

<aside
  @class([
    'relative flex h-full shrink-0 flex-col border-r border-border-sidebar bg-elevated transition-[width] duration-200',
    'w-[243px]' => ! $mobile,
    'w-[243px]' => $mobile,
  ])
  @if (! $mobile) data-desktop-sidebar @endif
  data-collapsed="false"
>
  <div class="flex h-[84px] shrink-0 items-center px-6" @if (! $mobile) data-sidebar-logo-row @endif>
    <a
      href="{{ route('dashboard') }}"
      class="flex w-full items-center gap-3 transition-opacity hover:opacity-90 focus:outline-none"
      @if (! $mobile) data-sidebar-item @endif
    >
      <img
        src="{{ asset('images/logo.png') }}"
        alt="WapApp"
        class="size-9 shrink-0 rounded-lg object-contain"
        width="36"
        height="36"
      >
      <span
        class="fd-logo min-w-0 text-xl font-bold tracking-tight text-text-primary whitespace-nowrap"
        @if (! $mobile) data-sidebar-label @endif
      >
        WapApp
      </span>
    </a>
  </div>

  <nav class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-4" data-sidebar-nav>
    @foreach ($navItems as $item)
      @php
        $hasChildren = ! empty($item['children'] ?? []);
        $isParentActive = $hasChildren
            ? $itemRouteActive($item) || collect($item['children'])->contains(fn ($child) => $childRouteActive($child))
            : $itemRouteActive($item) || ($active ?? '') === $item['route'];
        $isExpanded = $hasChildren && $isParentActive;
      @endphp

      @if ($hasChildren)
        @php $activeChild = collect($item['children'])->first(fn ($child) => $childRouteActive($child)); @endphp
        <div class="flex w-full flex-col items-end">
          <a
            href="{{ route($item['route']) }}"
            @class([
              'fd-nav-item flex w-full items-center gap-3 rounded-md p-3 transition-colors',
              'border-l-4 border-text-subtle bg-green-500 text-text-subtle' => $isParentActive,
              'text-blue-200 hover:bg-surface' => ! $isParentActive,
            ])
            @if ($isParentActive && ! $activeChild) data-sidebar-active aria-current="page" @endif
            @if (! $mobile) data-sidebar-item @endif
          >
            <x-icons.sidebar-icon :name="$item['icon']" :active="$isParentActive" class="size-5 shrink-0" />
            <span class="min-w-0 flex-1 whitespace-nowrap" @if (! $mobile) data-sidebar-label @endif>{{ $item['label'] }}</span>
            <img
              src="{{ asset('images/icons/sidebar/menu/chevron-right.svg') }}"
              alt=""
              @class(['size-4 shrink-0 transition-transform', 'rotate-90' => $isExpanded])
              @if (! $mobile) data-sidebar-chevron @endif
              width="16"
              height="16"
            >
          </a>

          @if ($isExpanded)
            @php $connectorHeight = (count($item['children']) * 40) + (max(0, count($item['children']) - 1) * 8); @endphp
            <div class="relative mt-2 flex w-full pl-[9px]" @if (! $mobile) data-sidebar-submenu @endif>
              <img
                src="{{ asset('images/icons/sidebar/submenu-connector-fd.svg') }}"
                alt=""
                class="absolute left-0 top-0 w-[9px]"
                style="height: {{ $connectorHeight }}px"
                width="9"
              >
              <div class="ml-[9px] flex w-full flex-col gap-2">
                @foreach ($item['children'] as $child)
                  @php $childActive = $childRouteActive($child); @endphp
                  <a
                    href="{{ route($child['route']) }}"
                    @class([
                      'fd-nav-item rounded-md p-2 transition-colors',
                      'bg-green-50 text-text-subtle' => $childActive,
                      'text-blue-200 hover:bg-surface' => ! $childActive,
                    ])
                    @if ($childActive) data-sidebar-active aria-current="page" @endif
                  >
                    <span @if (! $mobile) data-sidebar-label @endif>{{ $child['label'] }}</span>
                  </a>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      @else
        <a
          href="{{ route($item['route']) }}"
          @class([
            'fd-nav-item flex items-center gap-3 rounded-md p-3 transition-colors',
            'border-l-4 border-text-subtle bg-green-500 text-text-subtle' => $isParentActive,
            'text-blue-200 hover:bg-surface' => ! $isParentActive,
          ])
          @if ($isParentActive) data-sidebar-active aria-current="page" @endif
          @if (! $mobile) data-sidebar-item @endif
        >
          <x-icons.sidebar-icon :name="$item['icon']" :active="$isParentActive" class="size-5 shrink-0" />
          <span class="min-w-0 flex-1 whitespace-nowrap" @if (! $mobile) data-sidebar-label @endif>{{ $item['label'] }}</span>
          @if (! empty($item['badge']))
            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-green-50 text-[10px] font-bold leading-[1.5] text-green-500" @if (! $mobile) data-sidebar-badge @endif>{{ $item['badge'] }}</span>
          @endif
        </a>
      @endif
    @endforeach
  </nav>

  <div class="shrink-0 border-t border-border-sidebar p-4">
    <form action="{{ route('logout') }}" method="POST">
      @csrf
      <button
        type="submit"
        class="fd-nav-item flex w-full items-center gap-3 rounded-md p-3 text-danger hover:bg-surface"
        @if (! $mobile) data-sidebar-item @endif
      >
        <img src="{{ asset('images/icons/header/logout.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
        <span class="whitespace-nowrap" @if (! $mobile) data-sidebar-label @endif>Logout</span>
      </button>
    </form>
  </div>

  @if (! $mobile)
    <button
      type="button"
      data-desktop-sidebar-toggle
      class="absolute -right-3 top-7 z-[50] flex size-[22px] items-center justify-center rounded-full border border-blue-200 bg-elevated p-1 shadow-[0px_2px_8px_rgba(0,0,0,0.08)]"
      aria-label="Toggle sidebar"
      aria-expanded="true"
    >
      <img src="{{ asset('images/icons/sidebar/menu/chevron-right.svg') }}" alt="" class="size-3.5 rotate-180 transition-transform" data-sidebar-toggle-icon width="14" height="14">
    </button>
  @endif
</aside>
