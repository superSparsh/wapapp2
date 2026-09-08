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

<script>
const fieldTemplates = {
  logo: { label: 'Logo', required: true, placeholder: '', text: '', image_path: null },
  header: { label: 'Header', required: true, placeholder: 'Enter Header Details', text: '', required_message: 'Enter Required Message' },
  phone: { label: 'WhatsApp Number', required: true, placeholder: 'Enter WhatsApp Number', text: '', locked: true },
  first_name: { label: 'First Name', required: true, placeholder: 'Enter First Name', text: '' },
  last_name: { label: 'Last Name', required: true, placeholder: 'Enter Last Name', text: '' },
  paragraph: { label: 'Paragraph', required: false, placeholder: 'Enter Paragraph', text: '' },
  input: { label: 'Input', required: false, placeholder: 'Enter Input field', text: '' },
  dropdown: { label: 'Dropdown', required: false, placeholder: 'Enter Dropdown Field Label', text: '', options: ['Option 1', 'Option 2'] },
  checkbox: { label: 'Check Box', required: false, placeholder: 'Enter Check box details', text: '' },
};

const defaultFields = @json($defaultFields);

const canvas = document.getElementById('form-canvas');
const fieldsData = document.getElementById('fields-data');
const previewContainer = document.getElementById('preview-container');
let fields = [];

// Initialize with default fields
function initDefaults() {
  fields = [...defaultFields];
  renderCanvas();
}

function renderCanvas() {
  if (fields.length === 0) {
    canvas.innerHTML = '<p class="text-center text-sm text-text-muted py-8">Click on a field from the left panel to add it here.</p>';
    renderPreview();
    return;
  }

  canvas.innerHTML = fields.map((field, i) => {
    const tpl = fieldTemplates[field.type] || {};
    const label = field.label || tpl.label || field.type;
    const isReq = field.required ?? tpl.required ?? false;
    const isLocked = field.locked ?? tpl.locked ?? false;
    const escaped = (s) => String(s || '').replace(/"/g, '&quot;');

    let html = `<div class="flex flex-col gap-3 rounded-lg bg-elevated p-3" data-field-index="${i}">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold leading-[1.4] text-text-primary">${label}${isReq ? '<span class="text-red-500">*</span>' : ''}${isLocked ? '<span class="ml-2 text-[10px] font-normal text-text-muted bg-blue-50 px-1.5 py-0.5 rounded">Locked</span>' : ''}</p>
        ${isLocked ? '' : `<button type="button" onclick="removeField(${i})" class="flex size-5 items-center justify-center" aria-label="Remove"><img src="{{ asset('images/form-builder/trash.svg') }}" alt="" class="size-5" width="20" height="20"></button>`}
      </div>`;

    if (field.type === 'logo') {
      html += `<div class="flex items-center gap-3">
        <div class="flex h-[88px] min-w-0 flex-1 flex-col items-center justify-center rounded-md border border-dashed border-divider px-4 py-3">
          <img src="{{ asset('images/form-builder/upload-frame.svg') }}" alt="" class="mb-2 size-6" width="24" height="24">
          <p class="text-center text-xs font-medium text-text-body">Drag & Drop or <span class="text-green-500">choose</span> file to upload</p>
          <p class="mt-1 text-center text-[10px] font-medium text-text-body opacity-50">Only Support .png or .jpg</p>
        </div>
      </div>`;
    } else if (field.type === 'dropdown') {
      html += `<input type="text" value="${escaped(field.placeholder)}" placeholder="Enter Dropdown Field Label" oninput="updateField(${i}, 'placeholder', this.value)" class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none">`;
      html += `<p class="text-xs leading-[1.4] text-text-body">Dropdown Options</p>`;
      (field.options || []).forEach((opt, j) => {
        html += `<div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
          <input type="text" value="${escaped(opt)}" placeholder="Enter Option ${j+1}" oninput="updateOption(${i}, ${j}, this.value)" class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:outline-none">
          <button type="button" onclick="removeOption(${i}, ${j})" class="size-5 shrink-0"><img src="{{ asset('images/form-builder/trash.svg') }}" alt="" class="size-5" width="20" height="20"></button>
        </div>`;
      });
      html += `<div class="flex justify-end"><button type="button" onclick="addOption(${i})" class="text-xs leading-[1.4] text-green-500 underline">Add Dropdown Options</button></div>`;
    } else {
      html += `<input type="text" value="${escaped(field.placeholder)}" placeholder="${escaped(tpl.placeholder || 'Enter text')}" oninput="updateField(${i}, 'placeholder', this.value)" class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none">`;
    }

    if (field.type !== 'logo') {
      html += `<div class="flex items-center gap-2">
        <input type="checkbox" ${isReq ? 'checked' : ''} onchange="updateField(${i}, 'required', this.checked)" class="size-4 rounded border border-border-light bg-elevated">
        <span class="text-xs leading-[1.4] text-text-muted">Required Field</span>
      </div>`;
    }

    html += `</div>`;
    return html;
  }).join('');

  updateHiddenData();
  renderPreview();
}

function renderPreview() {
  if (fields.length === 0) {
    previewContainer.innerHTML = '<p class="text-center text-[9px] text-text-muted py-4">Add fields to see preview</p>';
    return;
  }

  let html = '';
  fields.forEach(field => {
    const label = field.label || field.type;
    const isReq = field.required ?? false;
    const placeholder = field.placeholder || '';

    if (field.type === 'logo') {
      html += `<div class="mb-1.5 flex items-center justify-center rounded border border-dashed border-divider bg-muted-surface/30 p-2"><span class="text-[8px] text-text-muted">Logo Image</span></div>`;
    } else if (field.type === 'header') {
      html += `<p class="mb-1 text-[10px] font-bold text-text-primary">${placeholder || label}</p>`;
    } else if (field.type === 'paragraph') {
      html += `<p class="mb-1 text-[8px] text-text-muted">${placeholder || 'Paragraph text...'}</p>`;
    } else if (field.type === 'dropdown') {
      html += `<div class="mb-1.5"><label class="block text-[8px] text-text-muted mb-0.5">${label}${isReq ? ' *' : ''}</label><select class="w-full rounded border border-border bg-elevated p-1 text-[8px]" disabled><option>${placeholder || 'Select...'}</option>${(field.options || []).map(o => `<option>${o}</option>`).join('')}</select></div>`;
    } else if (field.type === 'checkbox') {
      html += `<div class="mb-1.5 flex items-center gap-1"><input type="checkbox" disabled class="size-2.5 rounded border-border"><span class="text-[8px] text-text-body">${label}${isReq ? ' *' : ''}</span></div>`;
    } else {
      html += `<div class="mb-1.5"><label class="block text-[8px] text-text-muted mb-0.5">${label}${isReq ? ' *' : ''}</label><input type="text" placeholder="${placeholder}" class="w-full rounded border border-border bg-elevated p-1 text-[8px]" disabled></div>`;
    }
  });

  previewContainer.innerHTML = html;
}

function addField(type) {
  // Prevent duplicate locked fields
  if (fieldTemplates[type]?.locked && fields.some(f => f.type === type)) return;
  // Also prevent duplicate first_name/last_name
  if (['first_name', 'last_name'].includes(type) && fields.some(f => f.type === type)) return;

  fields.push({ ...fieldTemplates[type], type });
  renderCanvas();
}

function removeField(index) {
  // Don't remove locked fields
  if (fields[index]?.locked) return;
  fields.splice(index, 1);
  renderCanvas();
}

function updateField(index, key, value) {
  if (fields[index]) {
    fields[index][key] = value;
    updateHiddenData();
    renderPreview();
  }
}

function updateOption(fi, oi, value) {
  if (fields[fi] && fields[fi].options) {
    fields[fi].options[oi] = value;
    updateHiddenData();
    renderPreview();
  }
}

function addOption(fi) {
  if (fields[fi]) {
    if (!fields[fi].options) fields[fi].options = [];
    fields[fi].options.push('New Option');
    renderCanvas();
  }
}

function removeOption(fi, oi) {
  if (fields[fi] && fields[fi].options) {
    fields[fi].options.splice(oi, 1);
    renderCanvas();
  }
}

function updateHiddenData() {
  fieldsData.value = JSON.stringify(fields);
}

document.querySelectorAll('[data-add-field]').forEach(btn => {
  btn.addEventListener('click', () => addField(btn.dataset.addField));
});

document.getElementById('form-builder-create').addEventListener('submit', (e) => {
  fieldsData.value = JSON.stringify(fields);
});

(function bindActivateToggle() {
  const input = document.getElementById('activate-input');
  const toggle = document.querySelector('[data-activate-toggle]');
  if (!input || !(toggle instanceof HTMLElement)) return;

  toggle.addEventListener('click', (e) => {
    e.preventDefault();
    const next = input.value !== '1';
    input.value = next ? '1' : '0';
    toggle.setAttribute('aria-checked', next ? 'true' : 'false');
    toggle.classList.toggle('bg-green-500', next);
    toggle.classList.toggle('bg-green-50', !next);
    const knob = toggle.querySelector('span');
    if (knob instanceof HTMLElement) {
      knob.classList.toggle('left-[24px]', next);
      knob.classList.toggle('left-[2px]', !next);
    }
  });
})();

// Initialize with default fields on page load
initDefaults();
</script>
