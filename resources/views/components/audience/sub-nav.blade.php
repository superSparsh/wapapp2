@props(['active' => null])

@php
$listId = request('list');
$items = [
    ['route' => 'audience.overview', 'label' => 'Overview'],
    ['route' => 'audience.settings', 'label' => 'Settings'],
    ['route' => 'audience.subscribers', 'label' => 'Subscribers', 'matches' => ['audience.subscribers.empty', 'audience.subscribers.detail', 'audience.subscribers.import']],
    ['route' => 'audience.segments', 'label' => 'Segments'],
    ['route' => 'audience.forms', 'label' => 'Forms / pages'],
    ['route' => 'audience.list-fields', 'label' => 'Manage list fields'],
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

<nav class="flex w-full overflow-x-auto border-b-2 border-blue-50">
  @foreach ($items as $item)
    <a
      href="{{ route($item['route'], $listId ? ['list' => $listId] : []) }}"
      @class([
        'fd-tab flex flex-1 items-center justify-center whitespace-nowrap px-9 py-2.5 text-sm font-medium text-text-body transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $isActive($item),
        'hover:bg-surface' => ! $isActive($item),
      ])
    >
      {{ $item['label'] }}
    </a>
  @endforeach
</nav>
