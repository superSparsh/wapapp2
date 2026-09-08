@props([
    'title',
    'description' => null,
    'icon' => null,
])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]']) }}>
  <div class="border-b border-divider bg-surface/40 px-4 py-3 sm:px-5">
    <div class="flex items-start gap-3">
      @if ($icon)
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
          <img src="{{ asset($icon) }}" alt="" class="size-5" width="20" height="20">
        </span>
      @endif
      <div class="min-w-0">
        <h2 class="text-base font-semibold leading-[1.4] text-text-primary">{{ $title }}</h2>
        @if ($description)
          <p class="mt-0.5 text-sm leading-[1.5] text-text-subtle opacity-70">{{ $description }}</p>
        @endif
      </div>
    </div>
  </div>
  <div class="flex flex-col gap-4 p-4 sm:p-5">
    {{ $slot }}
  </div>
</section>
