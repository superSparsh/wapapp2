@props(['title', 'subtitle' => null, 'size' => 'lg'])

<div @class([
  'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
  'mb-0' => $size === 'sm',
  'mb-4' => $size !== 'sm',
])>
  <div class="flex flex-col gap-1">
    <h1 @class([
      'fd-page-title',
      'text-xl' => $size === 'sm',
      'text-2xl' => $size !== 'sm',
    ])>{{ $title }}</h1>
    @if ($subtitle)
      <p class="fd-page-note max-w-3xl">{{ $subtitle }}</p>
    @endif
  </div>
  @if (isset($actions))
    <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
  @endif
</div>
