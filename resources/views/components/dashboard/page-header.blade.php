@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-1 p-4">
  <h1 class="fd-page-title">{{ $title }}</h1>
  @if ($subtitle)
    <p class="fd-page-note !opacity-50">{{ $subtitle }}</p>
  @endif
</div>
