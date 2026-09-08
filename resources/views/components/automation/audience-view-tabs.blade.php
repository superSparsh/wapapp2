@props([
    'campaign',
    'active' => 'audience',
])

@php
  $tabs = [
    'audience' => [
      'label' => 'Audience',
      'route' => 'automation.drip.audience',
    ],
    'timeline' => [
      'label' => 'Timeline',
      'route' => 'automation.drip.audience-empty',
    ],
  ];
@endphp

<div class="w-full" data-drip-audience-tabs>
  <div class="relative flex w-full items-center">
    @foreach ($tabs as $key => $tab)
      <a
        href="{{ route($tab['route'], $campaign) }}"
        data-drip-audience-tab="{{ $key }}"
        @class([
          'min-w-0 flex-1 py-2 text-center text-base font-medium leading-[1.5] transition-colors',
          'text-green-500' => $active === $key,
          'text-text-muted hover:text-text-body' => $active !== $key,
        ])
      >
        {{ $tab['label'] }}
      </a>
    @endforeach
    <div
      class="pointer-events-none absolute top-7 h-[3px] w-1/2 rounded-t-full bg-green-500 transition-[left]"
      data-drip-audience-tab-indicator
      style="left: {{ $active === 'timeline' ? '50%' : '0' }};"
    ></div>
  </div>
</div>
