@php
  $categories = [
    ['Basic Input', 'message-text.svg', false],
    ['Selection', 'hierarchy-3.svg', false],
    ['Display', 'gallery.svg', false],
  ];

  $fieldCategories = [
    'Basic Input' => [
      ['text', 'Text Input', 'message-notif.svg'],
      ['email', 'Email', 'message-text.svg'],
      ['phone', 'Phone Number', 'messages.svg'],
      ['number', 'Number', 'document-text.svg'],
      ['password', 'Password / Passcode', 'document-text.svg'],
      ['paragraph', 'Paragraph / Text Area', 'message-text.svg'],
      ['date', 'Date Picker', 'clock.svg'],
    ],
    'Selection' => [
      ['radio', 'Single Choice (Radio)', 'messages.svg'],
      ['checkbox', 'Multi Choice (Checkbox)', 'hierarchy-3.svg'],
      ['dropdown', 'Dropdown', 'routing-2.svg'],
      ['opt-in', 'Opt-in Checkbox', 'messages.svg'],
    ],
    'Display' => [
      ['large-heading', 'Large Heading', 'message-text.svg'],
      ['small-heading', 'Small Heading', 'message-text.svg'],
      ['text-display', 'Text Body', 'message-text.svg'],
      ['caption', 'Caption Text', 'message-text.svg'],
      ['image', 'Image', 'gallery.svg'],
      ['footer', 'Footer Button', 'more.svg'],
    ],
  ];

  $flowJson = $flow->flow_json ?? ['screens' => [], 'first_screen' => null];
  $screenCount = $flow->screenCount();
  $fieldCount = $flow->fieldCount();
@endphp

<x-layouts.app title="{{ $flow->name }} - Flow Builder" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface" data-builder-workspace>
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
          <div class="flex items-center gap-2">
            <a href="{{ route('whatsapp-flows.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to list</a>
          </div>
          <div class="flex flex-wrap items-center gap-2 min-h-[40px]">
            <div id="flow-name-display-wrap" class="flex items-center gap-2">
              <h1 id="flow-name-display" class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }}</h1>
              <button type="button" id="edit-flow-name-btn" class="rounded p-1 text-text-muted hover:bg-muted-surface hover:text-text-body cursor-pointer" title="Rename Flow">
                <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
              </button>
            </div>
            <form id="flow-name-form" class="hidden flex flex-wrap items-center gap-2" data-update-url="{{ route('whatsapp-flows.update', $flow) }}">
              @csrf
              @method('PUT')
              <input type="text" id="flow-name-input" value="{{ $flow->name }}" class="rounded-lg border border-divider bg-surface px-3 py-1.5 text-base font-bold text-text-primary focus:border-green-500 focus:outline-none w-auto max-w-[280px]">
              <button type="submit" class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-3.5 py-2 text-xs font-semibold text-white hover:bg-green-600 transition-colors shadow-sm cursor-pointer">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save
              </button>
              <button type="button" id="cancel-flow-name" class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-divider bg-surface px-3 py-2 text-xs font-semibold text-text-body hover:bg-muted-surface transition-colors cursor-pointer">
                Cancel
              </button>
            </form>
          </div>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Build screens with form fields &bull; Define navigation rules &bull; Preview as it appears in WhatsApp
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 shrink-0 xl:justify-end">
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
            data-action="preview-flow"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
            title="Toggle preview"
          >
            <img src="{{ asset('images/automation/eye.svg') }}" alt="" class="size-5" width="20" height="20">
            Preview
          </button>
          <button
            type="button"
            data-action="save-flow"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            <img src="{{ asset('images/automation/ram-save.svg') }}" alt="" class="size-5" width="20" height="20">
            Save Flow
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
            data-action="delete-flow"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-red-400 bg-red-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-red-500 transition-colors hover:bg-red-100"
          >
            <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-4" width="16" height="16">
            Delete
          </button>
          @if (! $flow->isActive())
            <form action="{{ route('whatsapp-flows.publish', $flow) }}" method="POST" class="inline" id="publish-flow-form">
              @csrf
              <button
                type="submit"
                id="publish-flow-btn"
                @disabled(! $flow->isDraftSynced() || $screenCount === 0)
                title="{{ $flow->isDraftSynced() ? 'Publish to WhatsApp' : 'Save as draft before publishing' }}"
                class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-[#0356fb] px-4 py-3 text-sm font-semibold leading-[1.5] text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
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
      <x-ui.builder-maximize-toolbar :show-flow-actions="true" />
      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif

      <div id="flow-builder-status" class="hidden rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700"></div>

      <div class="relative flex flex-col gap-6">
        {{-- Category Tabs with Dropdowns positioned directly below each tab --}}
        <div class="relative z-30 grid gap-4 md:grid-cols-3">
          @foreach ($categories as [$label, $icon])
            <div class="relative category-tab-wrapper">
              <button
                type="button"
                data-category="{{ $label }}"
                class="category-tab flex w-full items-center gap-2 rounded-lg border border-solid border-green-100 bg-elevated px-2 py-1.5 text-left transition-colors hover:bg-green-50"
              >
                <div class="flex min-w-0 flex-1 items-center gap-2">
                  <span class="p-2">
                    <img src="{{ asset('images/automation/' . $icon) }}" alt="" class="size-5 shrink-0" width="20" height="20">
                  </span>
                  <span class="min-w-0 flex-1 truncate p-2 text-sm font-semibold leading-[1.5] text-text-subtle">{{ $label }}</span>
                </div>
                <span class="flex w-9 items-center justify-center p-2">
                  <img src="{{ asset('images/automation/arrow-down.svg') }}" alt="" class="size-4 shrink-0 transition-transform duration-200 category-arrow" width="16" height="16">
                </span>
              </button>

              {{-- Dropdown options directly under this specific tab --}}
              <div
                class="category-dropdown hidden absolute top-full left-0 right-0 z-40 mt-1.5 flex flex-col gap-1.5 rounded-xl border border-divider bg-elevated p-2 shadow-xl max-h-[340px] overflow-y-auto"
                data-palette-category="{{ $label }}"
              >
                @foreach ($fieldCategories[$label] ?? [] as [$type, $fieldLabel, $fieldIcon])
                  <div
                    class="palette-field flex cursor-grab items-center gap-3 rounded-lg bg-green-50 px-3 py-2.5 hover:bg-green-100 transition-colors"
                    draggable="true"
                    data-field-type="{{ $type }}"
                    data-field-label="{{ $fieldLabel }}"
                  >
                    <div class="flex min-w-0 flex-1 items-center gap-2.5">
                      <img src="{{ asset('images/automation/' . $fieldIcon) }}" alt="" class="size-4 shrink-0" width="16" height="16">
                      <span class="min-w-0 flex-1 text-xs font-medium leading-[1.5] text-text-subtle">{{ $fieldLabel }}</span>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>

        <div class="flex gap-4">
          {{-- Left Sidebar: Screen List --}}
          <div class="w-[240px] shrink-0 rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold text-text-primary">Screens</h3>
              <button type="button" data-action="add-screen" class="rounded bg-green-50 p-1 text-green-500 hover:bg-green-100" title="Add Screen">
                <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-4" width="16" height="16">
              </button>
            </div>
            <div id="screen-list" class="mt-3 flex flex-col gap-1">
              {{-- Screens rendered by JS --}}
            </div>
          </div>

          {{-- Center: Field Builder Canvas --}}
          <div
            id="flow-canvas"
            class="relative min-h-[635px] flex-1 overflow-hidden rounded-lg bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]"
            data-flow-id="{{ $flow->id }}"
            data-flow-uuid="{{ $flow->uuid }}"
            data-save-url="{{ route('whatsapp-flows.data.save', $flow) }}"
            data-load-url="{{ route('whatsapp-flows.data', $flow) }}"
            data-export-url="{{ route('whatsapp-flows.export', $flow) }}"
            data-import-url="{{ route('whatsapp-flows.import') }}"
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
                  <span id="screen-count-badge" class="text-xs font-semibold leading-[1.5] whitespace-nowrap text-text-muted">Screens: {{ $screenCount }}</span>
                </div>
                <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-solid border-text-subtle bg-blue-50 px-4 py-3">
                  <img src="{{ asset('images/automation/hierarchy-2.svg') }}" alt="" class="size-5" width="20" height="20">
                  <span id="field-count-badge" class="text-xs font-semibold leading-[1.5] whitespace-nowrap text-text-muted">Fields: {{ $fieldCount }}</span>
                </div>
              </div>

              {{-- Screen header --}}
              <div id="screen-editor-header" class="relative z-10 mb-4 mt-3 flex items-center justify-between border-b border-divider pb-3">
                <div class="flex items-center gap-2">
                  <div id="screen-title-display-wrap" class="flex items-center gap-2">
                    <h2 id="current-screen-title" class="text-base font-semibold text-text-primary" title="Double click to edit title">Select a screen</h2>
                    <button
                      type="button"
                      id="edit-screen-title-btn"
                      class="hidden rounded p-1 text-text-muted hover:bg-muted-surface hover:text-text-body cursor-pointer"
                      title="Edit screen title"
                    >
                      <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
                    </button>
                  </div>
                  <form id="screen-title-form" class="hidden flex items-center gap-2">
                    <input
                      type="text"
                      id="screen-title-input"
                      class="rounded-lg border border-divider bg-surface px-3 py-1.5 text-sm font-semibold text-text-primary focus:border-green-500 focus:outline-none min-w-[200px]"
                      placeholder="Screen title"
                    >
                    <button
                      type="submit"
                      class="inline-flex items-center gap-1.5 rounded-lg bg-green-500 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-green-600 transition-colors shadow-sm cursor-pointer"
                    >
                      <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                      Save
                    </button>
                    <button
                      type="button"
                      id="cancel-screen-title"
                      class="inline-flex items-center gap-1 rounded-lg border border-divider bg-surface px-3 py-1.5 text-xs font-semibold text-text-body hover:bg-muted-surface transition-colors cursor-pointer"
                    >
                      Cancel
                    </button>
                  </form>
                </div>
                <div class="flex items-center gap-2">
                  <button type="button" data-action="delete-screen" class="hidden rounded bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-500 hover:bg-red-100 cursor-pointer">Delete Screen</button>
                </div>
              </div>

              {{-- Fields container --}}
              <div id="fields-container" class="relative z-10 flex min-h-[300px] flex-1 flex-col gap-3">
                <div id="empty-screen-hint" class="flex flex-col items-center justify-center gap-2 py-20 text-center">
                  <p class="text-sm font-medium text-text-muted">Select a screen from the left or create a new one</p>
                </div>
              </div>

              {{-- Navigation rules --}}
              <div id="nav-rules-section" class="hidden relative z-10 mt-6 border-t border-divider pt-4">
                <h3 class="mb-2 text-sm font-semibold text-text-primary">Navigation</h3>
                <div class="flex items-center gap-3">
                  <label class="text-xs font-medium text-text-body">Next Screen:</label>
                  <select id="next-screen-select" class="rounded-lg border border-divider bg-surface px-3 py-2 text-xs text-text-body focus:border-green-500 focus:outline-none">
                    <option value="">(End / Success)</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {{-- Right Panel: Field Config --}}
          <div id="field-config-panel" class="hidden w-[320px] shrink-0 rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="flex items-center justify-between border-b border-divider pb-3">
              <h3 id="config-panel-title" class="text-sm font-semibold text-text-primary">Field Config</h3>
              <button type="button" id="close-config-panel" class="text-text-muted hover:text-text-body">&times;</button>
            </div>
            <div id="config-panel-body" class="mt-3 flex flex-col gap-3">
              {{-- Dynamic config rendered by JS --}}
            </div>
          </div>

          {{-- Preview Panel: WhatsApp phone mockup --}}
          <div id="preview-panel" class="hidden w-[340px] shrink-0">
            <div class="sticky top-4 rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-text-primary">Preview</h3>
                <button type="button" id="close-preview-panel" class="text-text-muted hover:text-text-body">&times;</button>
              </div>
              {{-- Phone frame --}}
              <div class="mx-auto w-[280px]">
                <div class="rounded-[28px] border-[3px] border-text-muted bg-surface p-2 shadow-lg">
                  {{-- Status bar --}}
                  <div class="flex items-center justify-between px-3 py-1">
                    <span class="text-[9px] font-semibold text-text-muted">9:41</span>
                    <div class="flex items-center gap-1">
                      <span class="block size-1 rounded-full bg-text-muted"></span>
                      <span class="block size-1 rounded-full bg-text-muted"></span>
                      <span class="block h-1.5 w-3 rounded-sm border border-text-muted"></span>
                    </div>
                  </div>
                  {{-- WA header --}}
                  <div class="flex items-center gap-2 rounded-t-lg bg-[#075e54] px-3 py-2">
                    <svg class="size-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
                    <span class="flex-1 text-xs font-semibold text-white" id="preview-flow-title">{{ $flow->name }}</span>
                  </div>
                  {{-- Screen content --}}
                  <div id="preview-content" class="min-h-[380px] rounded-b-lg bg-[#efeae2] p-3">
                    <p class="py-8 text-center text-xs text-text-muted">Select a screen to preview</p>
                  </div>
                  {{-- Bottom bar --}}
                  <div class="mt-2 flex items-center justify-between rounded-lg bg-surface px-3 py-2">
                    <span class="text-[10px] text-text-muted" id="preview-screen-indicator">Screen 1 of 1</span>
                    <span class="rounded bg-[#075e54] px-3 py-1 text-[10px] font-semibold text-white" id="preview-next-btn">Next</span>
                  </div>
                </div>
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

  @push('scripts')
  {{-- Flow builder module (separate from UI handlers so import failure doesn't break them) --}}
<script type="module">
  import { initWhatsappFlowBuilder } from '{{ asset('js/whatsapp-flows/flow-builder.js') }}';
  document.addEventListener('DOMContentLoaded', function () {
    initWhatsappFlowBuilder({
      flowId: {{ $flow->id }},
      flowJson: @json($flowJson),
    });
  });
</script>

{{-- UI handlers (regular script, runs regardless of module import) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Inline name editing ──
    var nameDisplay = document.getElementById('flow-name-display');
    var nameDisplayWrap = document.getElementById('flow-name-display-wrap') || (nameDisplay ? nameDisplay.parentElement : null);
    var nameForm = document.getElementById('flow-name-form');
    var editBtn = document.getElementById('edit-flow-name-btn');
    var cancelBtn = document.getElementById('cancel-flow-name');
    var nameInput = document.getElementById('flow-name-input');

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            if (nameDisplayWrap) nameDisplayWrap.classList.add('hidden');
            nameForm.classList.remove('hidden');
            nameInput.focus();
            nameInput.select();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            nameForm.classList.add('hidden');
            if (nameDisplayWrap) nameDisplayWrap.classList.remove('hidden');
        });
    }

    if (nameForm) {
        nameForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var updateUrl = nameForm.dataset.updateUrl;
            var csrf = document.querySelector('meta[name="csrf-token"]').content;

            fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ name: nameInput.value.trim() }),
            }).then(function (resp) {
                if (resp.ok) {
                    nameDisplay.textContent = nameInput.value.trim();
                    nameForm.classList.add('hidden');
                    if (nameDisplayWrap) nameDisplayWrap.classList.remove('hidden');
                    var previewTitle = document.getElementById('preview-flow-title');
                    if (previewTitle) previewTitle.textContent = nameInput.value.trim();
                }
            }).catch(function () {
                if (window.showAppAlert) { window.showAppAlert('Failed to update name.', 'Error'); } else { alert('Failed to update name'); }
            });
        });
    }

    // ── Delete Flow ──
    var deleteBtn = document.querySelector('[data-action="delete-flow"]');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            if (!window.showAppConfirm) {
                if (!confirm('Are you sure you want to delete this flow? This action cannot be undone.')) return;
                performDelete();
                return;
            }
            window.showAppConfirm({
                message: 'Delete this flow? This action cannot be undone.',
                title: 'Delete flow',
                variant: 'danger',
                confirmLabel: 'Delete',
            }).then(function (confirmed) {
                if (confirmed) performDelete();
            });
        });
    }

    function performDelete() {
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        fetch('{{ route('whatsapp-flows.destroy', $flow) }}', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
        }).then(function (resp) {
            if (resp.ok || resp.status === 302) {
                window.location.href = '{{ route('whatsapp-flows.index') }}';
            } else {
                if (window.showAppAlert) {
                    window.showAppAlert('Failed to delete flow.', 'Error');
                } else {
                    alert('Failed to delete flow.');
                }
            }
        }).catch(function () {
            if (window.showAppAlert) { window.showAppAlert('Failed to delete flow.', 'Error'); } else { alert('Failed to delete flow.'); }
        });
    }

    // ── Preview toggle ──
    var previewBtn = document.querySelector('[data-action="preview-flow"]');
    var previewPanel = document.getElementById('preview-panel');
    var closePreviewBtn = document.getElementById('close-preview-panel');

    if (previewBtn && previewPanel) {
        previewBtn.addEventListener('click', function () {
            var isHidden = previewPanel.classList.contains('hidden');
            previewPanel.classList.toggle('hidden');
            if (isHidden) {
                // Trigger preview render
                window.dispatchEvent(new CustomEvent('flow-preview-render'));
            }
        });
    }

    if (closePreviewBtn && previewPanel) {
        closePreviewBtn.addEventListener('click', function () {
            previewPanel.classList.add('hidden');
        });
    }

    // ── Import modal close buttons ──
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = btn.closest('[data-modal]');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    });

    // ── Config panel close ──
    var closeConfigBtn = document.getElementById('close-config-panel');
    if (closeConfigBtn) {
        closeConfigBtn.addEventListener('click', function () {
            document.getElementById('field-config-panel').classList.add('hidden');
        });
    }
});
</script>

<style>
/* Maximize: hide layout chrome for true fullscreen */
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

.builder-maximized #fields-container {
  flex: 1 !important;
  min-height: 0 !important;
  overflow: auto !important;
}
</style>
  @endpush
</x-layouts.app>
