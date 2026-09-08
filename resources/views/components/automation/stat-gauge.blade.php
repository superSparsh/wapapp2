@props([
    'label',
    'percent',
    'count' => '16 / 20',
    'percentColor' => '#9ca3af',
    'bgColor' => '#f1f1f2',
    'arc' => 'gauge-sent-arc.svg',
    'failed' => false,
    'historyHref' => null,
])

@php
  $historyHref = $historyHref ?? route('automation.drip.statistics.detail');
@endphp

<div
  class="flex min-h-[200px] w-full min-w-0 flex-col items-center gap-2 overflow-hidden rounded-2xl p-4 sm:p-6"
  style="background-color: {{ $bgColor }}"
>
  <div class="flex w-full items-start justify-center pb-2 sm:pb-4">
    <p class="text-center text-lg font-semibold leading-normal tracking-[-0.2px] text-text-body sm:text-xl">
      {{ $label }}
    </p>
  </div>

  <div class="relative h-[88px] w-full max-w-[210px] shrink-0 sm:h-[102px]">
    <img
      src="{{ asset('images/automation/gauge-track.svg') }}"
      alt=""
      class="absolute top-0 left-1/2 h-[88px] w-[calc(100%-8px)] max-w-[209px] -translate-x-1/2 sm:h-[102px]"
      width="209"
      height="102"
    >

    @if ($failed)
      <img
        src="{{ asset('images/automation/gauge-failed-arc.svg') }}"
        alt=""
        class="absolute top-[28px] left-1/2 h-[60px] w-[41px] -translate-x-[84px] sm:top-[32px] sm:h-[70px]"
        width="41"
        height="70"
      >
    @else
      <img
        src="{{ asset('images/automation/gauge-cap.svg') }}"
        alt=""
        class="absolute top-[28px] left-1/2 h-[60px] w-[41px] -translate-x-[84px] sm:top-[32px] sm:h-[70px]"
        width="41"
        height="70"
      >
      <img
        src="{{ asset('images/automation/' . $arc) }}"
        alt=""
        class="absolute top-0 left-1/2 h-[88px] w-[calc(100%-20px)] max-w-[189px] -translate-x-1/2 sm:h-[102px]"
        width="189"
        height="102"
      >
    @endif

    <p
      class="absolute bottom-[42px] left-1/2 -translate-x-1/2 translate-y-1/2 text-center text-2xl font-medium leading-normal tracking-[-1.59px] sm:bottom-[49px] sm:text-[32px]"
      style="color: {{ $percentColor }}"
    >
      {{ $percent }}
    </p>
  </div>

  <p class="text-center text-base font-semibold leading-normal text-text-body sm:text-xl">
    {{ $count }}
  </p>

  <div class="flex min-h-0 flex-1 flex-col items-center justify-end pt-2 sm:pt-3">
    <a href="{{ $historyHref }}" class="flex items-center gap-1">
      <span class="text-sm font-normal leading-normal tracking-[-0.3px] text-[#63aa42]">
        View History
      </span>
      <img
        src="{{ asset('images/automation/view-history-arrow.svg') }}"
        alt=""
        class="size-3.5"
        width="14"
        height="14"
      >
    </a>
  </div>
</div>
