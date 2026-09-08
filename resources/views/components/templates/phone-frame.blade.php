@props([
    'size' => 'default',
    'class' => '',
])

@php
    $isCompact = $size === 'compact';
@endphp

@if ($isCompact)
  <div {{ $attributes->merge(['class' => "relative mx-auto w-full max-w-[360px] {$class}"]) }}>
    <div class="relative aspect-[274/551] w-full">
      <div class="pointer-events-none absolute inset-[0_1.93px] rounded-[40px] border border-white/60 shadow-[inset_0px_0px_5px_0px_rgba(0,0,0,0.3)]">
        <div class="absolute inset-0 rounded-[40px] bg-muted-surface"></div>
      </div>
      <div class="absolute inset-[2.5px_4.5px] rounded-[37px] bg-black"></div>
      <div class="absolute left-0 top-[16%] h-[3.5%] w-[2px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute left-0 top-[23%] h-[7%] w-[2px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute left-0 top-[32.5%] h-[7%] w-[2px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute right-0 top-[25.5%] h-[11.5%] w-[2px] rounded-br-[1px] rounded-tr-[1px] bg-border shadow-[inset_-1px_0px_2px_0px_white]"></div>
      <div class="absolute inset-[2.5%_5.9%] overflow-hidden rounded-[26px] bg-elevated">
        <img
          src="{{ asset('images/templates/phone-bg.png') }}"
          alt=""
          class="absolute inset-0 size-full rounded-[5px] object-cover"
          width="242"
          height="550"
        >
        <x-ui.phone-preview-header-name size="compact" />
        <div class="relative z-10 flex min-h-full w-full flex-col overflow-hidden p-2">
          {{ $slot }}
        </div>
      </div>
    </div>
  </div>
@else
  <div {{ $attributes->merge(['class' => "relative mx-auto w-full max-w-[425px] {$class}"]) }}>
    <div class="relative h-[856px] w-full">
      <div class="pointer-events-none absolute inset-[0_3px] rounded-[62px] border border-white/60 shadow-[inset_0px_0px_8px_0px_rgba(0,0,0,0.3)]">
        <div class="absolute inset-0 rounded-[62px] bg-muted-surface"></div>
      </div>
      <div class="absolute inset-[4px_7px] rounded-[58px] bg-black"></div>
      <div class="absolute left-0 top-[136px] h-[30px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute left-0 top-[198px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute left-0 top-[278px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
      <div class="absolute right-0 top-[220px] h-[100px] w-[3px] rounded-br-[1px] rounded-tr-[1px] bg-border shadow-[inset_-1px_0px_2px_0px_white]"></div>
      <div class="absolute inset-[22px_25px] overflow-hidden rounded-[40px] bg-elevated">
        <img
          src="{{ asset('images/templates/phone-bg.png') }}"
          alt=""
          class="absolute inset-0 size-full rounded-[8px] object-cover"
          width="375"
          height="854"
        >
        <x-ui.phone-preview-header-name size="default" />
        <div class="relative z-10 min-h-full w-full overflow-hidden p-2">
          {{ $slot }}
        </div>
      </div>
    </div>
  </div>
@endif
