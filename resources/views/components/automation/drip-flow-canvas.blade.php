@props([
    'wide' => false,
    'showMaximize' => false,
    'campaign' => null,
    'showEditFlow' => false,
    'editable' => false,
    'fillHeight' => false,
    'nodeTypes' => [],
    'nodeOptions' => [],
    'categories' => [],
    'legacyActions' => [],
    'saveUrl' => '',
    'loadUrl' => '',
    'templatesListUrl' => '',
    'audiences' => [],
])

@php
  use App\Domains\Drip\Support\DripNodeCatalog;

  $hasFlowData = $campaign && $campaign->hasFlowData();
  $nodes = [];
  if ($hasFlowData) {
    $data = $campaign->exported_data;
    if (! empty($data['nodes']) && is_array($data['nodes'])) {
      $nodes = $data['nodes'];
    }
  }

  $flatNodeTypes = $nodeOptions !== []
    ? $nodeOptions
    : collect($nodeTypes)->flatMap(function ($items, $category) {
        return collect($items)->map(fn ($item) => [
          'type' => $item[0],
          'label' => $item[1],
          'icon' => $item[2] ?? 'message-notif.svg',
          'comingSoon' => $item[3] ?? false,
          'category' => $category,
          'description' => '',
        ]);
      })->values()->all();

  $pickerActions = $legacyActions !== []
    ? $legacyActions
    : DripNodeCatalog::legacyPickerActions();
@endphp

@if ($editable)
  <div
    data-drip-flow-editor
    data-save-url="{{ $saveUrl }}"
    data-load-url="{{ $loadUrl }}"
    data-templates-url="{{ $templatesListUrl }}"
    data-node-types='@json($flatNodeTypes)'
    data-audiences='@json($audiences)'
    data-trigger-label="{{ $campaign ? $campaign->triggerLabel() : 'New contact subscribes to list' }}"
    @class([
      'relative flex min-w-0 shrink-0 flex-col',
      'h-full min-h-0' => $fillHeight,
      'w-full max-w-[760px]' => ! $wide,
      'w-full lg:w-[962px] lg:max-w-[760px] lg:shrink-0' => $wide,
    ])
  >
@endif

<div
  data-drip-canvas
  @class([
    'relative flex shrink-0 flex-col justify-between self-stretch overflow-hidden rounded-lg p-3',
    'min-h-[900px]' => ! $fillHeight,
    'min-h-0 flex-1' => $fillHeight,
    'w-full max-w-[760px]' => ! $wide && ! $editable,
    'w-full lg:w-[962px] lg:max-w-[760px] lg:shrink-0' => $wide && ! $editable,
    'h-full w-full max-w-none' => $editable,
  ])
>
  <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-lg bg-elevated">
    <img
      src="{{ asset('images/automation/flow-canvas-bg.png') }}"
      alt=""
      class="size-full object-cover opacity-40"
      width="760"
      height="640"
    >
  </div>

  <div class="relative z-10 flex w-full shrink-0 items-center gap-2">
    <div @class([
      'flex min-w-0 flex-1 items-center border-b border-border-sidebar bg-elevated/80 px-4 py-2',
      'justify-center' => ! $showMaximize && ! $editable,
    ])>
      <p class="text-xl font-semibold leading-[1.5] text-text-primary {{ ($showMaximize || $editable) ? 'whitespace-nowrap' : 'text-center' }}">
        Automation when the following trigger condition met
      </p>
    </div>

    @if ($editable)
      <button
        type="button"
        data-drip-save-flow
        class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-3 py-2 text-xs font-semibold text-primary-2 transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
      >
        <img src="{{ asset('images/automation/ram-save.svg') }}" alt="" class="size-4" width="16" height="16">
        Save Flow
      </button>
    @endif

    @if ($showEditFlow && $campaign && ! $editable)
      <a
        href="{{ route('automation.drip.design', $campaign) }}"
        class="flex shrink-0 items-center gap-1 rounded-lg border border-solid border-green-500 bg-green-50 px-3 py-2 text-xs font-semibold text-green-500 transition-colors hover:bg-green-100"
      >
        <img src="{{ asset('images/automation/hierarchy-3.svg') }}" alt="" class="size-4" width="16" height="16">
        Edit Flow
      </a>
    @endif

    @if ($showMaximize)
      <button
        type="button"
        data-drip-minimize
        hidden
        class="hidden fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-elevated px-3 py-2 text-xs font-semibold text-text-body transition-colors hover:bg-muted-surface"
        title="Restore canvas"
      >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M19 12H5m7-7-7 7 7 7"/>
        </svg>
        Minimize
      </button>
      <button
        type="button"
        data-drip-maximize
        class="flex shrink-0 flex-col items-center justify-center rounded-lg border border-solid border-text-subtle bg-blue-50 p-2"
        aria-label="Maximize canvas"
        aria-pressed="false"
      >
        <img src="{{ asset('images/automation/maximize.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    @endif
  </div>

  <div
    @class([
      'relative z-10 mx-auto flex w-full max-w-[420px] flex-col items-center py-6',
      'min-h-[520px] flex-1 justify-center' => ! $fillHeight,
      'min-h-0 flex-1 justify-start overflow-y-auto' => $fillHeight,
    ])
    @if ($editable) data-drip-flow-stack @endif
  >
    @if ($editable)
      <div class="relative flex w-full flex-col items-center pb-6" data-drip-flow-nodes aria-live="polite">
        {{-- JS renders trigger, nodes, connectors, and add buttons --}}
      </div>
    @else
      <div class="relative flex w-full flex-col items-center">
        <div class="relative z-10 rounded-lg border-2 border-solid border-green-500 bg-green-50 p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]">
          <div class="flex items-center gap-2">
            <img src="{{ asset('images/automation/play-circle.svg') }}" alt="" class="size-5" width="20" height="20">
            <span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-text-subtle">
              {{ $campaign ? $campaign->triggerLabel() : 'New contact subscribes to list' }}
            </span>
          </div>
          <img src="{{ asset('images/automation/flow-connector-dot.svg') }}" alt="" class="absolute -top-[5px] left-1/2 size-1.5 -translate-x-1/2" width="6" height="6">
          <img src="{{ asset('images/automation/flow-connector-dot.svg') }}" alt="" class="absolute -bottom-1 left-1/2 size-1.5 -translate-x-1/2" width="6" height="6">
        </div>

        @if ($hasFlowData && count($nodes) > 0)
          @foreach ($nodes as $node)
            <div class="flex h-8 w-px items-center justify-center">
              <img src="{{ asset('images/automation/flow-connector-line.svg') }}" alt="" class="h-8 w-px" width="1" height="32">
            </div>
            <div class="relative z-10 flex size-10 items-center justify-center rounded-full border border-solid border-green-500 bg-elevated p-2">
              <img src="{{ asset('images/automation/add-circle.svg') }}" alt="" class="size-6" width="24" height="24">
            </div>
            <div class="flex h-8 w-px items-center justify-center">
              <img src="{{ asset('images/automation/flow-connector-line.svg') }}" alt="" class="h-8 w-px" width="1" height="32">
            </div>
            <div class="relative z-10 rounded-lg bg-[#2c3c5e] p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]">
              <div class="flex items-center gap-2">
                <img src="{{ asset('images/automation/play-circle-dark.svg') }}" alt="" class="size-5" width="20" height="20">
                <span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-[#eaecef]">
                  {{ $node['data']['label'] ?? $node['type'] ?? 'Node' }}
                </span>
              </div>
            </div>
          @endforeach
          <div class="flex h-8 w-px items-center justify-center">
            <img src="{{ asset('images/automation/flow-connector-line.svg') }}" alt="" class="h-8 w-px" width="1" height="32">
          </div>
        @else
          <div class="flex h-8 w-px items-center justify-center">
            <img src="{{ asset('images/automation/flow-connector-line.svg') }}" alt="" class="h-8 w-px" width="1" height="32">
          </div>
          <div class="relative z-10 flex size-10 items-center justify-center rounded-full border border-solid border-green-500 bg-elevated p-2">
            <img src="{{ asset('images/automation/add-circle.svg') }}" alt="" class="size-6" width="24" height="24">
          </div>
          <div class="flex h-7 w-px items-center justify-center">
            <img src="{{ asset('images/automation/flow-connector-line-short.svg') }}" alt="" class="h-7 w-px" width="1" height="27">
          </div>
          <div class="relative z-10 rounded-lg bg-[#2c3c5e] p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]">
            <div class="flex items-center gap-2">
              <img src="{{ asset('images/automation/play-circle-dark.svg') }}" alt="" class="size-5" width="20" height="20">
              <span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-[#eaecef]">Add nodes to your flow</span>
            </div>
          </div>
          <div class="flex h-8 w-px items-center justify-center">
            <img src="{{ asset('images/automation/flow-connector-line.svg') }}" alt="" class="h-8 w-px" width="1" height="32">
          </div>
        @endif

        <div class="relative z-10 flex size-10 items-center justify-center rounded-full border border-solid border-green-500 bg-elevated p-2">
          <img src="{{ asset('images/automation/add-circle.svg') }}" alt="" class="size-6" width="24" height="24">
        </div>
      </div>
    @endif
  </div>

  <div class="relative z-10 flex shrink-0 items-end justify-between">
    @if ($wide)
      <div class="flex h-[120px] w-[148px] flex-col gap-2.5 bg-muted-surface pt-12 pr-[95px] pb-[62px] pl-6">
        <div class="h-[13px] w-[33px] bg-border"></div>
        <div class="h-[13px] w-[33px] bg-border"></div>
      </div>
    @else
      <div></div>
    @endif
    <div class="ml-auto flex flex-col items-end">
      <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-blue-50 px-2 py-3" aria-hidden="true">
        <img src="{{ asset('images/automation/zoom-add.svg') }}" alt="" class="size-6" width="24" height="24">
        <img src="{{ asset('images/automation/minus.svg') }}" alt="" class="size-6" width="24" height="24">
      </div>
    </div>
  </div>
</div>

@if ($editable)
  <div
    class="fixed inset-0 z-[80] hidden items-center justify-center bg-[rgba(0,0,0,0.6)] p-4"
    data-drip-node-picker
    role="dialog"
    aria-modal="true"
    aria-labelledby="drip-node-picker-title"
    aria-hidden="true"
  >
    <div class="relative z-10 flex max-h-[90vh] w-full max-w-[640px] flex-col gap-4 overflow-hidden rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h2 id="drip-node-picker-title" class="text-2xl font-bold leading-[1.5] text-text-primary">Add an Action</h2>
          <p class="mt-2 text-sm font-normal leading-[1.5] text-text-subtle">
            {{ config('drip-nodes.intro') }}
          </p>
        </div>
        <button type="button" data-drip-picker-close aria-label="Close" class="shrink-0 text-2xl leading-none text-text-muted hover:text-text-body">&times;</button>
      </div>

      <div class="flex max-h-[min(52vh,420px)] flex-col gap-2 overflow-y-auto pr-1">
        @foreach ($pickerActions as $option)
          <button
            type="button"
            data-drip-add-node="{{ $option['type'] }}"
            data-drip-node-label="{{ $option['label'] }}"
            data-drip-node-icon="{{ $option['icon'] }}"
            @disabled($option['comingSoon'] ?? false)
            @class([
              'flex w-full items-start gap-3 rounded-xl border border-border bg-elevated px-3 py-3 text-left transition-colors',
              'hover:border-green-500 hover:bg-green-50' => ! ($option['comingSoon'] ?? false),
              'cursor-not-allowed opacity-50' => $option['comingSoon'] ?? false,
            ])
          >
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-green-50">
              <img src="{{ asset('images/automation/' . ($option['icon'] ?? 'message-notif.svg')) }}" alt="" class="size-5" width="20" height="20">
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-2">
                <span class="text-sm font-semibold leading-[1.4] text-text-primary">{{ $option['label'] }}</span>
                @if ($option['comingSoon'] ?? false)
                  <span class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-600">Coming Soon</span>
                @endif
              </span>
              @if (! empty($option['description']))
                <span class="mt-1 block text-xs font-normal leading-[1.5] text-text-subtle">{{ $option['description'] }}</span>
              @endif
            </span>
          </button>
        @endforeach
      </div>
    </div>
  </div>
  </div>
@endif
