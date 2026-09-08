@props([
    'flow' => null,
    'categories' => [],
    'nodeTypes' => [],
    'flowData' => ['nodes' => [], 'edges' => []],
    'saveUrl' => '',
    'loadUrl' => '',
    'exportUrl' => '',
    'publishUrl' => '',
    'backUrl' => '#',
    'backLabel' => 'Back to list',
])

@php
    $nodeCount = $flow ? $flow->nodeCount() : 0;
    $edgeCount = count($flowData['edges'] ?? []);
@endphp

<div class="flex flex-col bg-surface" data-builder-workspace>
    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <div class="flex items-center gap-2">
                    <a href="{{ $backUrl }}" class="text-sm text-text-subtle hover:text-text-body">&larr; {{ $backLabel }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <h1 id="flow-name-display" class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }}</h1>
                    <button type="button" id="edit-flow-name-btn" class="rounded p-1 text-text-muted hover:bg-muted-surface hover:text-text-body" title="Rename">
                        <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
                    </button>
                </div>
                <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
                    Drag nodes to canvas &bull; Connect handles for branching &bull; Quick replies use green handles
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    data-action="maximize"
                    aria-pressed="false"
                    class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                    title="Toggle fullscreen"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                    <span id="maximize-label" data-maximize-label>Maximize</span>
                </button>
                <button
                    type="button"
                    data-action="export-flow"
                    class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                >
                    <img src="{{ asset('images/automation/export-flow.svg') }}" alt="" class="size-5" width="20" height="20">
                    Export
                </button>
                <button
                    type="button"
                    data-action="import-flow"
                    class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                >
                    <img src="{{ asset('images/automation/import-flow.svg') }}" alt="" class="size-5" width="20" height="20">
                    Import
                </button>
                <button
                    type="button"
                    data-action="save-flow"
                    class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
                >
                    <img src="{{ asset('images/automation/ram-save.svg') }}" alt="" class="size-5" width="20" height="20">
                    Save Flow
                </button>
                @if ($publishUrl)
                    <form action="{{ $publishUrl }}" method="POST" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-[#0356fb] px-4 py-3 text-sm font-semibold leading-[1.5] text-white transition-opacity hover:opacity-90"
                        >
                            <img src="{{ asset('images/automation/send-flow.svg') }}" alt="" class="size-5" width="20" height="20">
                            Publish Flow
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <section id="builder-section" data-builder-maximize-section class="relative flex flex-col gap-4 bg-surface p-4 pt-0">
        <x-ui.builder-maximize-toolbar />
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Drag and drop required inputs and connect badges according to the flow&nbsp;
        </p>

        @if (session('status'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                {{ session('status') }}
            </div>
        @endif

        <div id="flow-builder-status" class="hidden rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700"></div>

        <div class="relative flex flex-col gap-8">
            {{-- Category Tabs --}}
            <div class="grid gap-2 md:grid-cols-5">
                @foreach ($categories as [$label, $icon, $active])
                    <button
                        type="button"
                        data-category="{{ $label }}"
                        @class([
                            'category-tab flex items-center gap-2 rounded-lg border border-solid border-green-100 px-2 py-1.5 text-left',
                            'bg-green-100' => $active,
                            'bg-elevated' => ! $active,
                        ])
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <span class="p-2">
                                <img src="{{ asset('images/automation/' . $icon) }}" alt="" class="size-5 shrink-0" width="20" height="20">
                            </span>
                            <span class="min-w-0 flex-1 truncate p-2 text-sm font-semibold leading-[1.5] text-text-subtle">{{ $label }}</span>
                        </div>
                        <span class="flex w-9 items-center justify-center p-2">
                            <img src="{{ asset('images/automation/arrow-down.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Node Palette --}}
            <div id="node-palette" class="absolute top-[55px] left-0 z-20 flex w-[226.6px] flex-col gap-1">
                @foreach ($nodeTypes as $category => $nodes)
                    <div class="palette-category" data-palette-category="{{ $category }}">
                        @foreach ($nodes as [$type, $label, $icon, $comingSoon])
                            <div
                                class="palette-node flex cursor-grab items-center gap-3 rounded-lg bg-green-50 py-3 pr-3 pl-8"
                                draggable="true"
                                data-node-type="{{ $type }}"
                                data-node-label="{{ $label }}"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <img src="{{ asset('images/automation/' . $icon) }}" alt="" class="size-4 shrink-0" width="16" height="16">
                                    <span class="min-w-0 flex-1 text-xs font-medium leading-[1.5] text-text-subtle">{{ $label }}</span>
                                </div>
                                @if ($comingSoon)
                                    <span class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold text-amber-600">Coming Soon</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Canvas --}}
            <div
                id="flow-canvas"
                class="relative min-h-[635px] overflow-hidden rounded-lg bg-elevated p-3"
                data-flow-id="{{ $flow->id }}"
                data-flow-uuid="{{ $flow->uuid }}"
                data-save-url="{{ $saveUrl }}"
                data-load-url="{{ $loadUrl }}"
                data-export-url="{{ $exportUrl }}"
            >
                <div class="relative flex h-full min-h-[610px] flex-col justify-between overflow-hidden rounded-lg p-3">
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-lg bg-elevated">
                        <img
                            src="{{ asset('images/automation/flow-canvas-bg.png') }}"
                            alt=""
                            class="absolute inset-0 size-full rounded-lg object-cover opacity-40"
                        >
                    </div>

                    {{-- Stats badges --}}
                    <div class="relative z-10 flex flex-wrap gap-4">
                        <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-blue-50 px-4 py-3">
                            <img src="{{ asset('images/automation/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                            <span id="node-count-badge" class="text-xs font-semibold leading-[1.5] whitespace-nowrap text-text-muted">Nodes: {{ $nodeCount }}</span>
                        </div>
                        <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-blue-50 px-4 py-3">
                            <img src="{{ asset('images/automation/hierarchy-2.svg') }}" alt="" class="size-5" width="20" height="20">
                            <span id="edge-count-badge" class="text-xs font-semibold leading-[1.5] whitespace-nowrap text-text-muted">Connections: {{ $edgeCount }}</span>
                        </div>
                    </div>

                    {{-- Nodes container --}}
                    <div id="nodes-container" class="relative z-10 min-h-[500px] w-full overflow-auto">
                        @if ($nodeCount === 0)
                            <div id="empty-canvas-hint" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center">
                                <p class="text-sm font-medium text-text-muted">Drag a node here to start building your flow</p>
                            </div>
                        @endif
                    </div>

                    {{-- Zoom controls --}}
                    <div class="relative z-10 ml-auto flex flex-col items-end gap-4">
                        <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-blue-50 px-2 py-3">
                            <button type="button" data-action="zoom-in" aria-label="Zoom in">
                                <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-6" width="24" height="24">
                            </button>
                            <button type="button" data-action="zoom-out" aria-label="Zoom out">
                                <img src="{{ asset('images/automation/minus.svg') }}" alt="" class="size-6" width="24" height="24">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- Import Modal --}}
<div id="modal-import-flow" class="fixed inset-0 z-50 hidden items-center justify-center bg-overlay" data-modal>
    <div class="w-full max-w-md rounded-xl bg-elevated p-6 shadow-xl">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-text-primary">Import Flow</h3>
            <button type="button" data-modal-close class="text-text-muted hover:text-text-body">&times;</button>
        </div>
        <form id="import-flow-form" class="mt-4 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="import-name" class="text-sm font-semibold text-text-body">Flow Name</label>
                <input type="text" id="import-name" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="import-file" class="text-sm font-semibold text-text-body">JSON File</label>
                <input type="file" id="import-file" accept=".json" required class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" data-modal-close class="rounded-lg border border-divider bg-surface px-4 py-2 text-sm font-semibold text-text-body">Cancel</button>
                <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Import</button>
            </div>
        </form>
    </div>
</div>

{{-- Node Config Panel --}}
<div id="node-config-panel" class="fixed inset-y-0 right-0 z-50 hidden w-[380px] overflow-y-auto bg-elevated shadow-[-4px_0px_12px_rgba(0,0,0,0.1)]">
    <div class="flex items-center justify-between border-b border-divider p-4">
        <h3 id="config-panel-title" class="text-base font-semibold text-text-primary">Configure Node</h3>
        <button type="button" id="close-config-panel" class="text-text-muted hover:text-text-body">&times;</button>
    </div>
    <div id="config-panel-body" class="p-4">
        {{-- Dynamic config form rendered by JS --}}
    </div>
</div>

@push('scripts')
<script type="module">
  import { initFlowBuilder } from '{{ asset('js/chatbot/flow-builder.js') }}';
  document.addEventListener('DOMContentLoaded', function () {
    initFlowBuilder(@json($flowData));
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Modal close
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('[data-modal]');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    });

    // Config panel close
    var closeConfigBtn = document.getElementById('close-config-panel');
    if (closeConfigBtn) {
        closeConfigBtn.addEventListener('click', function () {
            document.getElementById('node-config-panel').classList.add('hidden');
        });
    }
});
</script>

<style>
body.builder-fullscreen > .flex > .hidden.lg\:block,
body.builder-fullscreen > .flex > #mobile-sidebar,
body.builder-fullscreen > .flex > #sidebar-backdrop,
body.builder-fullscreen > .flex > div > header,
body.builder-fullscreen > .flex > div > .min-h-0 > main > .flex > .flex-col:first-child {
  display: none !important;
}

.builder-maximized {
  position: fixed !important;
  inset: 0 !important;
  z-index: 40 !important;
  background: var(--color-surface, #f5f5f5) !important;
  overflow: auto !important;
  padding: 1rem !important;
  margin: 0 !important;
  display: flex !important;
  flex-direction: column !important;
}

.builder-maximized #flow-canvas {
  flex: 1 !important;
  min-height: 0 !important;
}

.builder-maximized #flow-canvas > div {
  height: 100% !important;
  min-height: 0 !important;
}

.builder-maximized #nodes-container {
  flex: 1 !important;
  min-height: 0 !important;
  overflow: auto !important;
}
</style>
@endpush
