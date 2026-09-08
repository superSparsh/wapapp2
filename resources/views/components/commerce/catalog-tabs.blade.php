@props(['active' => 'products'])

@php
  $tabs = [
    'products' => ['label' => 'Products', 'route' => 'commerce.index'],
    'collections' => ['label' => 'Collections', 'route' => 'commerce.catalog'],
    'catalogues' => ['label' => 'Catalogues', 'route' => 'commerce.orders'],
  ];
@endphp

<div class="flex w-full max-w-[680px] border-b-2 border-blue-50">
  @foreach ($tabs as $key => $tab)
    <a
      href="{{ route($tab['route']) }}"
      @class([
        'fd-tab flex flex-1 items-center justify-center px-9 py-2.5 transition-colors',
        'border-b-2 border-green-500 bg-green-100' => $active === $key,
        'hover:bg-surface' => $active !== $key,
      ])
    >
      {{ $tab['label'] }}
    </a>
  @endforeach
</div>
