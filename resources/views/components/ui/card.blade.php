@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-border bg-elevated p-6 shadow-sm']) }}>
  @if ($title)
    <h2 class="mb-4 text-lg font-semibold text-text-primary">{{ $title }}</h2>
  @endif
  {{ $slot }}
</div>
