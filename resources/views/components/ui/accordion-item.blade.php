@props(['title', 'content' => null, 'open' => false, 'variant' => 'collapsed'])

@php
  $isExpanded = $variant === 'expanded' || $open;
@endphp

<details
  @class([
    'group overflow-hidden rounded-xl',
    'bg-green-50 p-6' => $isExpanded,
    'border border-[#8ac96d] p-6' => ! $isExpanded,
  ])
  {{ $isExpanded ? 'open' : '' }}
>
  <summary class="flex cursor-pointer list-none items-center justify-between gap-4 [&::-webkit-details-marker]:hidden">
    <h3 @class([
      'text-lg font-semibold leading-[1.55]',
      'text-text-body' => $isExpanded,
      'text-text-primary' => ! $isExpanded,
    ]) style="font-family: var(--font-display)">{{ $title }}</h3>
    <img
      src="{{ asset($isExpanded ? 'images/faq/icon.svg' : 'images/faq/icon-collapsed.svg') }}"
      alt=""
      @class([
        'size-[18px] shrink-0 transition-transform',
        'rotate-180' => $isExpanded,
      ])
      width="18"
      height="10"
    >
  </summary>
  @if ($content || $slot->isNotEmpty())
    <div class="mt-6 space-y-6 text-sm leading-[1.5] text-text-body" style="font-family: var(--font-display)">
      {{ $content ?? $slot }}
    </div>
  @endif
</details>
