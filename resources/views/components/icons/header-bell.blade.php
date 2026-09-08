@props(['count' => 0, 'class' => 'size-7'])

@php
  $badgeCount = max(0, (int) $count);
  $badgeLabel = $badgeCount > 9 ? '9+' : (string) $badgeCount;
@endphp

<span {{ $attributes->merge(['class' => "relative inline-block {$class}"]) }}>
    <img src="{{ asset('images/icons/header/bell-base.svg') }}" alt="" class="absolute inset-0 size-full" width="28" height="28">
    <img src="{{ asset('images/icons/header/bell-path-1.svg') }}" alt="" class="absolute inset-[12.5%_16.67%_29.17%_16.67%] size-[66%]" width="19" height="19">
    <img src="{{ asset('images/icons/header/bell-path-2.svg') }}" alt="" class="absolute inset-[70.83%_37.5%_12.5%_37.5%] size-[25%]" width="7" height="4">
    @if ($badgeCount > 0)
      <span data-notification-badge class="absolute -right-0.5 top-0 flex size-[18px] items-center justify-center">
          <img src="{{ asset('images/icons/header/bell-badge-bg.svg') }}" alt="" class="absolute size-[18px]" width="18" height="18">
          <span class="relative text-[11px] font-semibold leading-[14px] text-white" style="font-family: 'Public Sans', sans-serif">{{ $badgeLabel }}</span>
      </span>
    @endif
</span>
