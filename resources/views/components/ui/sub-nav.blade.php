@props(['items' => []])

<nav class="flex flex-wrap gap-0 overflow-x-auto border-b-2 border-blue-50">
  @foreach ($items as $item)
    <a
      href="{{ route($item['route']) }}"
      @class([
        'fd-tab whitespace-nowrap px-9 py-2.5 transition-colors',
        'border-b-2 border-green-500 bg-green-100' => request()->routeIs($item['route'] . '*') || request()->routeIs($item['route']),
        'hover:bg-surface' => ! request()->routeIs($item['route'] . '*') && ! request()->routeIs($item['route']),
      ])
    >
      {{ $item['label'] }}
    </a>
  @endforeach
</nav>
