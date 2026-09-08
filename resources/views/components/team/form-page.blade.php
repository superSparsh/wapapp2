@props([
    'title',
    'subtitle' => null,
    'backUrl',
    'backLabel' => 'Back',
    'layoutActive' => 'my-team.index',
    'documentTitle' => null,
    'showStepper' => false,
    'stepCurrent' => 1,
    'stepTotal' => 2,
    'stepLabel' => 'Details → Roles & access',
])

<x-layouts.app :title="($documentTitle ?? $title).' - WapApp'" :active="$layoutActive">
  <div class="flex flex-col bg-surface">
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 pb-8">
      <a
        href="{{ $backUrl }}"
        class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-text-subtle transition-colors hover:text-green-500"
      >
        <img src="{{ asset('images/icons/sidebar/menu/chevron-right.svg') }}" alt="" class="size-4 rotate-180 opacity-60" width="16" height="16">
        {{ $backLabel }}
      </a>

      <x-ui.page-header :title="$title" :subtitle="$subtitle" />

      @if ($showStepper)
        <div class="flex items-center gap-3 rounded-xl border border-green-50 bg-elevated px-4 py-3 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          @for ($step = 1; $step <= $stepTotal; $step++)
            @if ($step > 1)
              <div @class(['h-px w-6 shrink-0', $step <= $stepCurrent ? 'bg-green-500/30' : 'bg-border'])></div>
            @endif
            <div @class([
              'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
              'bg-green-500 text-white' => $step <= $stepCurrent,
              'border border-border bg-surface font-medium text-text-muted' => $step > $stepCurrent,
            ])>{{ $step }}</div>
          @endfor
          <p class="ml-1 text-sm text-text-subtle">{{ $stepLabel }}</p>
        </div>
      @endif

      {{ $slot }}
    </div>
  </div>
</x-layouts.app>
