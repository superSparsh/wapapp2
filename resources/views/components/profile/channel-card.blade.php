@props([
    'phone' => '+91 00000 00000',
    'displayName' => null,
    'quality' => 'GREEN',
    'limit' => '—',
    'connected' => true,
    'isDefault' => false,
    'href' => null,
])

@php
    $phoneLabel = str_starts_with((string) $phone, '+') ? (string) $phone : '+'.$phone;
@endphp

<div {{ $attributes->class(['relative flex w-full max-w-[360px] flex-col overflow-hidden rounded-2xl']) }} style="background: linear-gradient(122.44deg, #b4bafe 0%, #6dbb48 100%);">
  <div class="flex min-h-[218px] flex-1 flex-col justify-between gap-4 p-6 text-white">
    <div class="flex items-start justify-between gap-3">
      <span
        class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold leading-[1.5] text-white"
        style="background: linear-gradient(178.53deg, #6dbb48 0%, rgba(17, 153, 170, 0.56) 100%);"
      >
        <img src="{{ asset('images/profile/tick-circle.svg') }}" alt="" class="size-4" width="16" height="16">
        {{ $connected ? 'Connected' : 'Disconnected' }}
      </span>
      <div class="flex items-center gap-2">
        @if ($isDefault)
          <span class="rounded-full bg-white/20 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">Default</span>
        @endif
        @if ($connected)
          <img src="{{ asset('images/profile/verify.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
        @endif
      </div>
    </div>

    <div class="flex flex-col gap-1">
      @if (filled($displayName))
        <p class="text-sm font-medium leading-5 text-white/90">{{ $displayName }}</p>
      @endif
      <p class="text-2xl font-bold leading-8 text-white">{{ $phoneLabel }}</p>
    </div>

    <div class="flex w-full items-end justify-between gap-4 text-white">
      <div class="flex gap-8">
        <div>
          <p class="text-xs font-medium leading-[18px] text-white/80">Quality</p>
          <p class="text-sm font-semibold leading-5">{{ strtoupper((string) ($quality ?: '—')) }}</p>
        </div>
        <div>
          <p class="text-xs font-medium leading-[18px] text-white/80">Limit</p>
          <p class="text-sm font-semibold leading-5">{{ $limit ?: '—' }}</p>
        </div>
      </div>

      @if ($href)
        <a
          href="{{ $href }}"
          class="fd-btn inline-flex shrink-0 items-center justify-center rounded-lg bg-white px-3 py-2 text-xs font-semibold text-green-600 transition-opacity hover:opacity-90"
        >
          Update Profile
        </a>
      @endif
    </div>
  </div>
</div>
