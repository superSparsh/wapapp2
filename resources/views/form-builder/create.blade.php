<x-layouts.app title="Create Form - WapApp" active="form-builder.index">
  <div class="flex flex-col bg-surface">
    <form method="post" action="{{ route('form-builder.store') }}" id="form-builder-create" data-validate-form enctype="multipart/form-data">
      @csrf
      <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
          <div class="min-w-0 flex-1 flex flex-col gap-1">
            <h1 class="fd-page-title text-2xl">Create New Form</h1>
            <p class="fd-page-note">Build a signup form to collect leads and send WhatsApp messages.</p>
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-3 sm:pt-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium leading-[1.4] text-text-body">Active</span>
              <x-ui.toggle-switch :active="false" data-activate-toggle aria-label="Activate form" />
            </div>
            <a
              href="{{ route('form-builder.index') }}"
              class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-elevated px-5 py-2.5 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
            >
              Cancel
            </a>
            <button
              type="submit"
              class="fd-btn inline-flex items-center justify-center rounded-lg bg-green-500 px-5 py-2.5 text-sm font-semibold leading-[1.5] text-primary-2 transition-colors hover:opacity-90"
            >
              Save Form
            </button>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="flex flex-col gap-3">
            <label for="form-name" class="text-sm font-semibold leading-[1.4] text-text-primary">Form Name <span class="text-red-500">*</span></label>
            <input
              id="form-name"
              type="text"
              name="name"
              required
              placeholder="Enter form name"
              class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>
          <div class="flex flex-col gap-3">
            <label for="form-mail-list" class="text-sm font-semibold leading-[1.4] text-text-primary">Select a Mail List <span class="text-red-500">*</span></label>
            <select
              id="form-mail-list"
              name="list_id"
              required
              class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
              <option value="">-- Select a mail list --</option>
              @foreach ($mailLists as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <div class="flex flex-col gap-3">
            <label for="form-template" class="text-sm font-semibold leading-[1.4] text-text-primary">Select a Template <span class="text-red-500">*</span></label>
            <select
              id="form-template"
              name="template_id"
              required
              class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
              <option value="">-- Select a template --</option>
              @foreach ($templates as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <section class="grid items-start gap-2 px-4 pb-4 lg:grid-cols-[280px_minmax(0,1fr)_320px]">
        {{-- Available Fields (sticky while canvas scrolls) --}}
        <aside class="flex max-h-[calc(100vh-220px)] flex-col overflow-hidden rounded-lg bg-blue-50 pt-2 lg:sticky lg:top-4">
          <div class="flex shrink-0 flex-col gap-1 px-3">
            <h2 class="text-xl font-bold leading-[1.5] text-text-primary">Available Fields</h2>
            <p class="text-sm leading-[1.4] text-text-subtle opacity-50">Click on these fields to add</p>
          </div>
          <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-3 py-4">
            <div class="flex flex-col gap-3" id="available-fields">
              @foreach ($fieldTypes as $fieldType)
                <button
                  type="button"
                  data-add-field="{{ $fieldType->value }}"
                  class="flex w-full items-center gap-3 rounded-lg border border-border bg-elevated p-3 text-left transition-colors hover:border-green-500/40"
                >
                  <span class="flex shrink-0 items-center overflow-hidden rounded-md border border-green-500 bg-green-50 p-1.5">
                    <img src="{{ asset('images/form-builder/field-icon.png') }}" alt="" class="size-6 object-cover" width="24" height="24">
                  </span>
                  <span class="text-sm font-medium leading-[1.4] text-text-body">{{ $fieldType->label() }}</span>
                </button>
              @endforeach
            </div>
            <div class="flex items-start gap-2 rounded-xl bg-stat-blue/15 p-3.5">
              <img src="{{ asset('images/form-builder/info-circle.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
              <p class="text-xs leading-[1.4] text-text-muted">WhatsApp Number, First Name & Last Name are added by default. Logo and Header are always placed at the top.</p>
            </div>
          </div>
        </aside>

        {{-- Form Canvas --}}
        <div class="flex max-h-[calc(100vh-220px)] flex-col gap-3 overflow-hidden rounded-lg bg-blue-50 px-4 py-2">
          <div class="flex shrink-0 flex-col gap-1">
            <h2 class="text-xl font-bold leading-[1.5] text-text-primary">Form Canvas</h2>
            <p class="text-sm leading-[1.4] text-text-subtle opacity-50">Fill these fields</p>
          </div>

          <div id="form-canvas" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pr-1 pb-2">
            <p class="text-center text-sm text-text-muted py-8">Click on a field from the left panel to add it here.</p>
          </div>
        </div>

        {{-- Dynamic Form Preview (sticky while canvas scrolls) --}}
        <aside class="flex max-h-[calc(100vh-220px)] flex-col gap-2 overflow-hidden rounded-lg bg-blue-50 p-2 lg:sticky lg:top-4">
          <div class="flex shrink-0 flex-col gap-1 px-2">
            <h2 class="text-xl font-bold leading-[1.5] text-text-primary">Form Preview</h2>
            <p class="text-sm leading-[1.4] text-text-subtle opacity-50">Live preview of your form</p>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="flex justify-center px-1 py-2.5">
              <div class="relative h-[551px] w-[274px] shrink-0">
                <div class="pointer-events-none absolute inset-x-[2px] inset-y-0 rounded-[40px] border border-white/60 bg-muted-surface shadow-[inset_0px_0px_5px_0px_rgba(0,0,0,0.3)]"></div>
                <div class="absolute inset-[2.5px_4.5px] rounded-[37px] bg-black"></div>
                <div class="absolute inset-[14px_16px] overflow-hidden rounded-[26px] bg-elevated">
                  <img
                    src="{{ asset('images/form-builder/whatsapp-screen.png') }}"
                    alt=""
                    class="absolute inset-0 size-full rounded object-cover object-top"
                    width="242"
                    height="550"
                  >
                  <x-ui.phone-preview-header-name size="compact" />
                  <div id="preview-container" class="absolute left-1/2 top-[140px] w-[228px] -translate-x-1/2 max-h-[380px] overflow-y-auto rounded-tl-lg rounded-tr-lg rounded-br-lg border border-border bg-elevated p-2">
                    <p class="text-center text-[9px] text-text-muted py-4">Add fields to see preview</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </section>

      {{-- Hidden fields data (populated by JS) --}}
      <input type="hidden" name="fields" id="fields-data" value="[]">
      <input type="hidden" name="logo_path" id="logo-path">
      <input type="hidden" name="activate" id="activate-input" value="0">
    </form>
  </div>
</x-layouts.app>

@include('form-builder.partials.canvas-script', [
  'formId' => 'form-builder-create',
  'initialFields' => [],
  'defaultFields' => $defaultFields ?? [],
])
