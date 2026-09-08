@props([
    'series' => [],
])

@php
  $points = collect($series);
  $max = max(1, (int) $points->max('value'));
  $chartHeight = 140;
@endphp

<div class="relative min-h-[220px] w-full overflow-hidden rounded-xl border border-[#e6e9ee] bg-elevated p-4" data-subscribers-chart>
  @if ($points->isEmpty())
    <p class="py-16 text-center text-sm text-text-muted">No subscriber growth data yet.</p>
  @else
    <div class="flex items-end gap-3" style="height: {{ $chartHeight + 48 }}px;">
      @foreach ($points as $point)
        @php
          $value = (int) ($point['value'] ?? 0);
          $barPx = (int) round(($value / $max) * $chartHeight);
          $barPx = max($value > 0 ? 12 : 4, $barPx);
        @endphp
        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2">
          <span class="text-xs font-medium text-text-muted">{{ number_format($value) }}</span>
          <div
            class="w-full max-w-[48px] rounded-t-md bg-green-500/80"
            style="height: {{ $barPx }}px;"
            title="{{ $point['label'] }}: {{ number_format($value) }}"
          ></div>
          <span class="text-[11px] text-text-subtle">{{ $point['label'] }}</span>
        </div>
      @endforeach
    </div>
  @endif
</div>
