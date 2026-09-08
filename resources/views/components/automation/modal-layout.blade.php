@props(['title', 'subtitle' => null])

<div class="flex min-h-screen flex-col bg-surface">
  <div class="mx-auto m-4 flex w-full max-w-[1222px] flex-1 flex-col overflow-hidden rounded-[20px] bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex shrink-0 items-start gap-4 border-b border-border-light p-5">
      <a href="{{ route('chatbot.index') }}" class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border-light hover:bg-surface" aria-label="Back">
        <img src="{{ asset('images/inbox/modals/arrow-left.svg') }}" alt="" class="size-5" width="20" height="20">
      </a>
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">{{ $title }}</h1>
        @if ($subtitle)
          <p class="mt-1 text-sm leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">{{ $subtitle }}</p>
        @endif
      </div>
      <a href="{{ route('chatbot.index') }}" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface" aria-label="Close">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </a>
    </div>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
      {{ $slot }}
    </div>
  </div>
</div>
