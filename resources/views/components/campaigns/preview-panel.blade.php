@props(['title' => 'Message Preview', 'subtitle' => 'Template preview message look like'])

<div class="hidden shrink-0 lg:block lg:w-[458px]">
  <div class="mb-4 px-2">
    <h3 class="fd-section-title">{{ $title }}</h3>
    <p class="fd-page-note">{{ $subtitle }}</p>
  </div>
  <div class="h-[360px] rounded-lg bg-border lg:h-[458px]">
    @if (trim($slot))
      <div class="flex h-full items-center justify-center p-4">
        {{ $slot }}
      </div>
    @endif
  </div>
</div>
