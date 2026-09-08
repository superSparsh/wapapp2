@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-1">
  <h1 class="fd-page-title">{{ $title }}</h1>
  @if ($subtitle)
    <p class="fd-page-note max-w-[854px]">{{ $subtitle }}</p>
  @endif
</div>
