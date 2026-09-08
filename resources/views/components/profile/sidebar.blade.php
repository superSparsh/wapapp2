@props(['items' => null])

@php
  $navItems = $items ?? config('profile-navigation');
@endphp

<aside class="w-full shrink-0">
  <nav class="flex flex-col gap-2 rounded-xl border border-divider bg-elevated p-2 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
    @foreach ($navItems as $item)
      @php
        $routes = array_merge([$item['route']], $item['matches'] ?? []);
        $isActive = collect($routes)->contains(fn ($route) => request()->routeIs($route) || request()->routeIs($route . '.*'));
      @endphp
      <a
        href="{{ route($item['route']) }}"
        @class([
          'fd-nav-item rounded-md p-2 text-sm transition-colors',
          'bg-green-50 text-text-subtle' => $isActive,
          'text-blue-200 hover:bg-surface' => ! $isActive,
        ])
      >
        {{ $item['label'] }}
      </a>
    @endforeach
  </nav>
</aside>
