@props([
    'title' => 'Message Preview',
    'subtitle' => 'Template preview message look like',
    'size' => 'default',
])

<div class="flex flex-col gap-4 px-2 py-4">
  <div class="flex flex-col gap-1">
    <h3 class="fd-preview-title">{{ $title }}</h3>
    <p class="fd-preview-subtitle">{{ $subtitle }}</p>
  </div>

  <x-templates.phone-frame :size="$size">
    @if ($slot->isEmpty())
      <x-templates.message-preview-bubble :size="$size" />
    @else
      {{ $slot }}
    @endif
  </x-templates.phone-frame>
</div>
