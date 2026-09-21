@props(['count' => 0, 'class' => 'size-7'])

@php
  $badgeCount = max(0, (int) $count);
  $badgeLabel = $badgeCount > 9 ? '9+' : (string) $badgeCount;
@endphp

<span {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center text-text-primary {$class}"]) }}>
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="size-full" aria-hidden="true">
    <path
      d="M6 9.5C6 6.46243 8.46243 4 11.5 4H12.5C15.5376 4 18 6.46243 18 9.5V13.5L20 16.5H4L6 13.5V9.5Z"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <path
      d="M10 19C10.5 20.1 11.2 20.75 12 20.75C12.8 20.75 13.5 20.1 14 19"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
  @if ($badgeCount > 0)
    <span data-notification-badge class="absolute -right-1 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-[18px] text-white">
      {{ $badgeLabel }}
    </span>
  @endif
</span>
