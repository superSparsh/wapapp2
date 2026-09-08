<x-layouts.app title="{{ $flow->name }} - Flow Builder" active="automation.chatbot" mainOverflow="overflow-hidden">
  <div class="flex h-full min-h-0 flex-col bg-surface" data-builder-workspace>
    <div class="shrink-0 border-b border-divider bg-surface p-4 pb-5" data-builder-page-header>
      <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between xl:gap-6">
        <div class="flex min-w-0 flex-1 flex-col gap-1">
          <div class="flex items-center gap-2">
            <a href="{{ route('chatbot.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to list</a>
          </div>
          <div class="flex items-center gap-2">
            <h1 id="flow-name-display" class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }}</h1>
            <button type="button" id="edit-flow-name-btn" class="rounded p-1 text-text-muted hover:bg-muted-surface hover:text-text-body" title="Rename">
              <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
            </button>
          </div>
          <form id="flow-name-form" class="hidden flex items-center gap-2" data-update-url="{{ route('chatbot.update', $flow) }}">
            @csrf
            @method('PUT')
            <input type="text" id="flow-name-input" value="{{ $flow->name }}" class="rounded-lg border border-divider bg-surface px-3 py-1.5 text-sm font-bold text-text-primary focus:border-green-500 focus:outline-none">
            <button type="submit" class="rounded-lg bg-green-500 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
            <button type="button" id="cancel-flow-name" class="rounded-lg border border-divider px-3 py-1.5 text-xs font-semibold text-text-body">Cancel</button>
          </form>
          <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Build your WhatsApp chatbot here: drag nodes from the toolbar onto the canvas, click a node to configure its message or action, and connect the handles to define what happens next. Start with a welcome or template node and add trigger keywords so customers can begin the flow. When you are done, click <strong class="font-semibold text-text-subtle">Save Flow</strong> — you will be asked whether to enable the chatbot right after saving.
          </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-3 xl:flex-nowrap" data-builder-actions>
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
          <input type="file" id="chatbot-import-file" accept=".json" class="hidden">
          <button
            type="button"
            data-action="save-flow"
            id="chatbot-save-flow-btn"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-white transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/automation/ram-save.svg') }}" alt="" class="size-5 brightness-0 invert" width="20" height="20">
            <span data-save-flow-label>Save Flow</span>
          </button>
        </div>
      </div>
    </div>

    <section id="builder-section" data-builder-maximize-section class="relative flex min-h-0 flex-1 flex-col gap-4 bg-surface p-4 pt-4">
      <x-ui.builder-maximize-toolbar :show-flow-actions="true" />
      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <div
        id="chatbot-react-root"
        class="min-h-0 flex-1 overflow-hidden rounded-lg border border-divider bg-elevated"
        data-flow-id="{{ $flow->id }}"
        data-flow-uuid="{{ $flow->uuid }}"
        data-builder-data-url="{{ route('chatbot.builder-data', $flow) }}"
        data-save-url="{{ route('chatbot.data.save', $flow) }}"
        data-clear-cache-url="{{ route('chatbot.clear-cache', $flow) }}"
        data-toggle-url="{{ route('chatbot.toggle', $flow) }}"
        data-is-active="{{ $flow->isActive() ? '1' : '0' }}"
      ></div>
    </section>
  </div>

  @push('scripts')
    @viteReactRefresh
    @vite(['resources/js/chatbot/react-flow-builder.jsx'])

    <script>
      window.__CHATBOT_BUILDER_CONFIG__ = {
        flowId: @json((string) $flow->id),
        flowUuid: @json($flow->uuid),
        builderDataUrl: @json(route('chatbot.builder-data', $flow)),
        saveUrl: @json(route('chatbot.data.save', $flow)),
        clearCacheUrl: @json(route('chatbot.clear-cache', $flow)),
        toggleUrl: @json(route('chatbot.toggle', $flow)),
        isActive: @json($flow->isActive()),
        csrfToken: @json(csrf_token()),
      };

      document.addEventListener('DOMContentLoaded', function () {
        var nameDisplay = document.getElementById('flow-name-display');
        var nameForm = document.getElementById('flow-name-form');
        var editBtn = document.getElementById('edit-flow-name-btn');
        var cancelBtn = document.getElementById('cancel-flow-name');
        var nameInput = document.getElementById('flow-name-input');

        if (editBtn) {
          editBtn.addEventListener('click', function () {
            nameDisplay.parentElement.classList.add('hidden');
            nameForm.classList.remove('hidden');
            nameInput.focus();
            nameInput.select();
          });
        }

        if (cancelBtn) {
          cancelBtn.addEventListener('click', function () {
            nameForm.classList.add('hidden');
            nameDisplay.parentElement.classList.remove('hidden');
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
                nameDisplay.parentElement.classList.remove('hidden');
              }
            });
          });
        }

        var saveBtn = document.querySelectorAll('[data-action="save-flow"]');
        var exportBtn = document.querySelectorAll('[data-action="export-flow"]');
        var importBtn = document.querySelectorAll('[data-action="import-flow"]');
        var importFile = document.getElementById('chatbot-import-file');
        var saveLabels = document.querySelectorAll('[data-save-flow-label]');

        saveBtn.forEach(function (button) {
          button.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('chatbot:save-flow'));
          });
        });

        exportBtn.forEach(function (button) {
          button.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('chatbot:export-flow'));
          });
        });

        importBtn.forEach(function (button) {
          button.addEventListener('click', function () {
            if (importFile) {
              importFile.click();
            }
          });
        });

        if (importFile) {
          importFile.addEventListener('change', function (event) {
            window.dispatchEvent(new CustomEvent('chatbot:import-flow', {
              detail: { input: event },
            }));
            importFile.value = '';
          });
        }

        window.addEventListener('chatbot:save-state', function (event) {
          if (!saveLabels.length) {
            return;
          }

          var saving = Boolean(event.detail && event.detail.saving);
          saveBtn.forEach(function (button) {
            button.disabled = saving;
          });
          saveLabels.forEach(function (label) {
            label.textContent = saving ? 'Saving...' : 'Save Flow';
          });
        });
      });
    </script>

    <style>
      #chatbot-react-root {
        height: 100%;
        min-height: 0;
      }

      #chatbot-react-root .ant-layout {
        min-height: 0;
        height: 100%;
        background: transparent;
      }

      .builder-maximized {
        position: fixed !important;
        inset: 0 !important;
        z-index: 50 !important;
        height: 100vh !important;
        width: 100vw !important;
        background: var(--surface, #ffffff);
      }
    </style>
  @endpush
</x-layouts.app>
