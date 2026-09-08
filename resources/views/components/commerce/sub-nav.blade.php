@props(['active' => null])

@php
$items = [
    ['route' => 'commerce.index', 'label' => 'Products'],
    ['route' => 'commerce.orders', 'label' => 'Catalogues'],
    ['route' => 'commerce.catalog', 'label' => 'Orders', 'matches' => ['commerce.orders.detail']],
    ['route' => 'commerce.products', 'label' => 'Payments', 'matches' => ['commerce.product-detail']],
    ['route' => 'commerce.settings', 'label' => 'Settings'],
];

$isActive = function (array $item) use ($active): bool {
    if ($active && ($item['route'] === $active || in_array($active, $item['matches'] ?? [], true))) {
        return true;
    }

    $routes = array_merge([$item['route']], $item['matches'] ?? []);

    return collect($routes)->contains(
        fn (string $route) => request()->routeIs($route)
    );
};
@endphp

<nav class="flex flex-wrap gap-0 overflow-x-auto border-b-2 border-blue-50">
  @foreach ($items as $item)
    <a
      href="{{ route($item['route']) }}"
      @class([
        'fd-tab whitespace-nowrap px-9 py-2.5 transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $isActive($item),
        'hover:bg-surface' => ! $isActive($item),
      ])
    >
      {{ $item['label'] }}
    </a>
  @endforeach
</nav>
