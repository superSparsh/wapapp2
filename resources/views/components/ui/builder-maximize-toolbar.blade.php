@props([
  'showFlowActions' => false,
])

<div
  data-builder-maximize-toolbar
  hidden
  class="builder-maximize-toolbar hidden"
>
  @if ($showFlowActions)
    <div class="flex flex-wrap items-center justify-end gap-2 mt-3 px-5">
      <button
        type="button"
        data-action="export-flow"
        class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-3 py-2 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
      >
        <img src="{{ asset('images/automation/export-flow.svg') }}" alt="" class="size-4" width="16" height="16">
        Export
      </button>
      <button
        type="button"
        data-action="import-flow"
        class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-3 py-2 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
      >
        <img src="{{ asset('images/automation/import-flow.svg') }}" alt="" class="size-4" width="16" height="16">
        Import
      </button>
      <button
        type="button"
        data-action="save-flow"
        class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-3 py-2 text-sm font-semibold leading-[1.5] text-white transition-opacity hover:opacity-90"
      >
        <img src="{{ asset('images/automation/ram-save.svg') }}" alt="" class="size-4 brightness-0 invert" width="16" height="16">
        <span data-save-flow-label>Save Flow</span>
      </button>
      <button
        type="button"
        data-builder-minimize
        class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-text-subtle bg-elevated px-3 py-2 text-sm font-semibold leading-[1.5] text-text-body shadow-sm transition-colors hover:bg-muted-surface"
        title="Exit fullscreen"
      >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M19 12H5m7-7-7 7 7 7"/>
        </svg>
        Minimize
      </button>
    </div>
  @else
    <button
      type="button"
      data-builder-minimize
      class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-text-subtle bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-text-body shadow-sm transition-colors hover:bg-muted-surface"
      title="Exit fullscreen"
    >
      <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M19 12H5m7-7-7 7 7 7"/>
      </svg>
      Minimize
    </button>
  @endif
</div>
