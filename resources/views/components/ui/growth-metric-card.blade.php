@props(['label', 'value', 'badge', 'up' => true, 'icon' => 'graph', 'danger' => false])

<div @class([
  'flex flex-col gap-5 rounded-xl border bg-elevated p-5',
  'border-danger/50' => $danger,
  'border-border' => ! $danger,
])>
  <div @class([
    'flex size-12 items-center justify-center rounded-xl p-3',
    'bg-green-50' => ! $danger,
    'bg-danger/10' => $danger,
  ])>
    <x-icons.nav-icon :name="$icon" class="size-6" />
  </div>
  <div class="flex items-end justify-between gap-5">
    <div>
      <p class="text-base font-medium leading-[1.4] text-text-primary" style="font-family: var(--font-display)">{{ $label }}</p>
      <p class="mt-2 text-2xl font-bold leading-[38px] text-text-primary" style="font-family: var(--font-display)">{{ $value }}</p>
    </div>
    <span @class([
      'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-medium',
      'bg-green-50 text-green-700' => $up,
      'bg-danger/10 text-danger' => ! $up,
    ]) style="font-family: var(--font-display)">
      @if ($up)
        <img src="{{ asset('images/charts/arrow-up.svg') }}" alt="" class="size-3" width="12" height="12">
      @else
        <img src="{{ asset('images/charts/arrow-down.svg') }}" alt="" class="size-3" width="12" height="12">
      @endif
      {{ $badge }}
    </span>
  </div>
</div>
