@props(['label', 'value', 'icon' => null, 'trend' => null])

<div class="rounded-2xl border border-border bg-elevated p-5 shadow-sm">
  <div class="flex items-start justify-between gap-3">
    <div>
      <p class="text-sm font-medium text-text-muted">{{ $label }}</p>
      <p class="mt-2 text-2xl font-bold text-text-primary">{{ $value }}</p>
      @if ($trend)
        <p class="mt-1 text-xs font-medium text-green-500">{{ $trend }}</p>
      @endif
    </div>
    @if ($icon)
      <div class="flex size-10 items-center justify-center rounded-xl bg-green-50 text-green-500">
        {{ $icon }}
      </div>
    @endif
  </div>
</div>
