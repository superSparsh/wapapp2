{{--
  Shared form-builder canvas script (create + edit).
  Expects: #form-canvas, #fields-data, #preview-container, #logo-path
  Optional: $initialFields (array), $defaultFields (array), $formId (string form element id)
--}}
@php
  $formId = $formId ?? 'form-builder-create';
  $uploadLogoUrl = route('form-builder.upload-logo');
  $logoShowBase = url('/form-builder/logos');
  $storageBase = rtrim(asset('storage'), '/');
  $uploadIcon = asset('images/form-builder/upload-frame.svg');
  $trashIcon = asset('images/form-builder/trash.svg');
@endphp
<script>
(function () {
  const NO_REQUIRED_TYPES = new Set(['logo', 'header', 'paragraph']);
  const fieldTemplates = {
    logo: { label: 'Logo', required: false, placeholder: '', text: '', image_path: null },
    header: { label: 'Header', required: false, placeholder: 'Enter Header Details', text: '' },
    phone: { label: 'WhatsApp Number', required: true, placeholder: 'Enter WhatsApp Number', text: '', locked: true },
    first_name: { label: 'First Name', required: true, placeholder: 'Enter First Name', text: '' },
    last_name: { label: 'Last Name', required: true, placeholder: 'Enter Last Name', text: '' },
    paragraph: { label: 'Paragraph', required: false, placeholder: 'Enter Paragraph', text: '' },
    input: { label: 'Input', required: false, placeholder: 'Enter Input field', text: '' },
    dropdown: { label: 'Dropdown', required: false, placeholder: 'Enter Dropdown Field Label', text: '', options: ['Option 1', 'Option 2'] },
    checkbox: { label: 'Check Box', required: false, placeholder: 'Enter Check box details', text: '' },
  };

  const uploadLogoUrl = @json($uploadLogoUrl);
  const logoShowBase = @json($logoShowBase);
  const uploadIcon = @json($uploadIcon);
  const trashIcon = @json($trashIcon);
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const canvas = document.getElementById('form-canvas');
  const fieldsDataInput = document.getElementById('fields-data');
  const previewContainer = document.getElementById('preview-container');
  const logoPathInput = document.getElementById('logo-path');
  const formEl = document.getElementById(@json($formId));

  let fields = @json($initialFields ?? []);
  const defaultFields = @json($defaultFields ?? []);

  let dragFromIndex = null;

  function escaped(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function ensureDefaults() {
    (defaultFields.length ? defaultFields : [
      { type: 'phone', label: 'WhatsApp Number', required: true, placeholder: 'Enter WhatsApp Number', text: '', locked: true },
      { type: 'first_name', label: 'First Name', required: true, placeholder: 'Enter First Name', text: '' },
      { type: 'last_name', label: 'Last Name', required: true, placeholder: 'Enter Last Name', text: '' },
    ]).forEach((def) => {
      if (!fields.some((f) => f.type === def.type)) {
        fields.push({ ...def });
      }
    });

    fields = fields.map((field) => {
      if (NO_REQUIRED_TYPES.has(field.type)) {
        return { ...field, required: false };
      }
      return field;
    });
  }

  function storageUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path) || path.startsWith('blob:') || path.startsWith('/form-builder/logos/')) {
      return path;
    }
    return `${logoShowBase.replace(/\/$/, '')}/${String(path).replace(/^\/+/, '')}`;
  }

  function syncLogoPath() {
    if (!(logoPathInput instanceof HTMLInputElement)) return;
    const logoField = fields.find((f) => f.type === 'logo' && f.image_path);
    if (logoField?.image_path) {
      logoPathInput.value = logoField.image_path;
    }
  }

  function renderCanvas() {
    if (!canvas) return;

    if (fields.length === 0) {
      canvas.innerHTML = '<p class="text-center text-sm text-text-muted py-8">Click on a field from the left panel to add it here.</p>';
      updateHiddenData();
      renderPreview();
      return;
    }

    canvas.innerHTML = fields.map((field, i) => {
      const tpl = fieldTemplates[field.type] || {};
      const label = field.label || tpl.label || field.type;
      const isReq = NO_REQUIRED_TYPES.has(field.type) ? false : (field.required ?? tpl.required ?? false);
      const isLocked = field.locked ?? tpl.locked ?? false;
      const showRequired = !NO_REQUIRED_TYPES.has(field.type);

      let html = `<div class="flex flex-col gap-3 rounded-lg border border-transparent bg-elevated p-3 transition-colors" data-field-index="${i}" draggable="true">
        <div class="flex items-center justify-between gap-2">
          <div class="flex min-w-0 flex-1 items-center gap-2">
            <span class="cursor-grab select-none text-text-muted" title="Drag to reorder" aria-hidden="true">⋮⋮</span>
            <input type="text" value="${escaped(label)}" placeholder="Field label" oninput="window.FormBuilderCanvas.updateField(${i}, 'label', this.value)" class="min-w-0 flex-1 rounded-lg border border-border bg-elevated px-2.5 py-1.5 text-sm font-semibold leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none">
            ${isReq ? '<span class="shrink-0 text-red-500">*</span>' : ''}
            ${isLocked ? '<span class="shrink-0 text-[10px] font-normal text-text-muted bg-blue-50 px-1.5 py-0.5 rounded">Locked</span>' : ''}
          </div>
          <div class="flex shrink-0 items-center gap-1">
            <button type="button" onclick="window.FormBuilderCanvas.moveField(${i}, -1)" class="flex size-7 items-center justify-center rounded border border-border text-xs text-text-body hover:bg-muted-surface" aria-label="Move up" ${i === 0 ? 'disabled' : ''}>↑</button>
            <button type="button" onclick="window.FormBuilderCanvas.moveField(${i}, 1)" class="flex size-7 items-center justify-center rounded border border-border text-xs text-text-body hover:bg-muted-surface" aria-label="Move down" ${i === fields.length - 1 ? 'disabled' : ''}>↓</button>
            ${isLocked ? '' : `<button type="button" onclick="window.FormBuilderCanvas.removeField(${i})" class="flex size-5 items-center justify-center" aria-label="Remove"><img src="${trashIcon}" alt="" class="size-5" width="20" height="20"></button>`}
          </div>
        </div>`;

      if (field.type === 'logo') {
        const previewSrc = field.image_url || storageUrl(field.image_path);
        const preview = previewSrc
          ? `<img src="${escaped(previewSrc)}" alt="Logo preview" class="mb-2 max-h-14 object-contain">`
          : `<img src="${uploadIcon}" alt="" class="mb-2 size-6" width="24" height="24">`;
        html += `<label class="flex cursor-pointer items-center gap-3">
          <div class="flex h-[88px] min-w-0 flex-1 flex-col items-center justify-center rounded-md border border-dashed border-divider px-4 py-3 hover:border-green-500/50">
            ${preview}
            <p class="text-center text-xs font-medium text-text-body">Drag & Drop or <span class="text-green-500">choose</span> file to upload</p>
            <p class="mt-1 text-center text-[10px] font-medium text-text-body opacity-50">Only Support .png or .jpg</p>
            <input type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="sr-only" data-logo-upload="${i}">
          </div>
        </label>
        <p class="text-[11px] text-text-muted" data-logo-status="${i}">${field.image_path ? 'Logo uploaded' : ''}</p>`;
      } else if (field.type === 'dropdown') {
        html += `<input type="text" value="${escaped(field.placeholder)}" placeholder="Enter Dropdown Field Label" oninput="window.FormBuilderCanvas.updateField(${i}, 'placeholder', this.value)" class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none">`;
        html += `<p class="text-xs leading-[1.4] text-text-body">Dropdown Options</p>`;
        (field.options || []).forEach((opt, j) => {
          html += `<div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
            <input type="text" value="${escaped(opt)}" placeholder="Enter Option ${j + 1}" oninput="window.FormBuilderCanvas.updateOption(${i}, ${j}, this.value)" class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:outline-none">
            <button type="button" onclick="window.FormBuilderCanvas.removeOption(${i}, ${j})" class="size-5 shrink-0"><img src="${trashIcon}" alt="" class="size-5" width="20" height="20"></button>
          </div>`;
        });
        html += `<div class="flex justify-end"><button type="button" onclick="window.FormBuilderCanvas.addOption(${i})" class="text-xs leading-[1.4] text-green-500 underline">Add Dropdown Options</button></div>`;
      } else if (field.type === 'header' || field.type === 'paragraph') {
        html += `<input type="text" value="${escaped(field.placeholder || field.text)}" placeholder="${escaped(tpl.placeholder || 'Enter text')}" oninput="window.FormBuilderCanvas.updateField(${i}, 'placeholder', this.value); window.FormBuilderCanvas.updateField(${i}, 'text', this.value)" class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none">`;
      } else {
        html += `<input type="text" value="${escaped(field.placeholder)}" placeholder="${escaped(tpl.placeholder || 'Enter text')}" oninput="window.FormBuilderCanvas.updateField(${i}, 'placeholder', this.value)" class="rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none">`;
      }

      if (showRequired) {
        html += `<div class="flex items-center gap-2">
          <input type="checkbox" ${isReq ? 'checked' : ''} onchange="window.FormBuilderCanvas.updateField(${i}, 'required', this.checked)" class="size-4 rounded border border-border-light bg-elevated">
          <span class="text-xs leading-[1.4] text-text-muted">Required Field</span>
        </div>`;
      }

      html += `</div>`;
      return html;
    }).join('');

    bindDragAndDrop();
    bindLogoUploads();
    updateHiddenData();
    renderPreview();
  }

  function renderPreview() {
    if (!previewContainer) return;

    if (fields.length === 0) {
      previewContainer.innerHTML = '<p class="text-center text-[9px] text-text-muted py-4">Add fields to see preview</p>';
      return;
    }

    let html = '';
    fields.forEach((field) => {
      const label = field.label || field.type;
      const isReq = NO_REQUIRED_TYPES.has(field.type) ? false : (field.required ?? false);
      const placeholder = field.placeholder || field.text || '';

      if (field.type === 'logo') {
        const previewSrc = field.image_url || storageUrl(field.image_path);
        html += previewSrc
          ? `<div class="mb-1.5 flex items-center justify-center"><img src="${escaped(previewSrc)}" alt="Logo" class="max-h-10 object-contain"></div>`
          : `<div class="mb-1.5 flex items-center justify-center rounded border border-dashed border-divider bg-muted-surface/30 p-2"><span class="text-[8px] text-text-muted">Logo Image</span></div>`;
      } else if (field.type === 'header') {
        html += `<p class="mb-1 text-[10px] font-bold text-text-primary">${escaped(placeholder || label)}</p>`;
      } else if (field.type === 'paragraph') {
        html += `<p class="mb-1 text-[8px] text-text-muted">${escaped(placeholder || 'Paragraph text...')}</p>`;
      } else if (field.type === 'dropdown') {
        html += `<div class="mb-1.5"><label class="block text-[8px] text-text-muted mb-0.5">${escaped(label)}${isReq ? ' *' : ''}</label><select class="w-full rounded border border-border bg-elevated p-1 text-[8px]" disabled><option>${escaped(placeholder || 'Select...')}</option>${(field.options || []).map((o) => `<option>${escaped(o)}</option>`).join('')}</select></div>`;
      } else if (field.type === 'checkbox') {
        html += `<div class="mb-1.5 flex items-center gap-1"><input type="checkbox" disabled class="size-2.5 rounded border-border"><span class="text-[8px] text-text-body">${escaped(label)}${isReq ? ' *' : ''}</span></div>`;
      } else {
        html += `<div class="mb-1.5"><label class="block text-[8px] text-text-muted mb-0.5">${escaped(label)}${isReq ? ' *' : ''}</label><input type="text" placeholder="${escaped(placeholder)}" class="w-full rounded border border-border bg-elevated p-1 text-[8px]" disabled></div>`;
      }
    });

    previewContainer.innerHTML = html;
  }

  function addField(type) {
    if (fieldTemplates[type]?.locked && fields.some((f) => f.type === type)) return;
    if (['first_name', 'last_name', 'logo'].includes(type) && fields.some((f) => f.type === type)) return;

    fields.push({ ...fieldTemplates[type], type });
    renderCanvas();
  }

  function removeField(index) {
    if (fields[index]?.locked) return;
    fields.splice(index, 1);
    renderCanvas();
  }

  function moveField(index, delta) {
    const next = index + delta;
    if (next < 0 || next >= fields.length) return;
    const [item] = fields.splice(index, 1);
    fields.splice(next, 0, item);
    renderCanvas();
  }

  function updateField(index, key, value) {
    if (!fields[index]) return;
    fields[index][key] = value;
    if (key === 'label' || key === 'placeholder' || key === 'text' || key === 'required') {
      updateHiddenData();
      renderPreview();
      return;
    }
    updateHiddenData();
    renderPreview();
  }

  function updateOption(fi, oi, value) {
    if (!fields[fi]?.options) return;
    fields[fi].options[oi] = value;
    updateHiddenData();
    renderPreview();
  }

  function addOption(fi) {
    if (!fields[fi]) return;
    if (!fields[fi].options) fields[fi].options = [];
    fields[fi].options.push('New Option');
    renderCanvas();
  }

  function removeOption(fi, oi) {
    if (!fields[fi]?.options) return;
    fields[fi].options.splice(oi, 1);
    renderCanvas();
  }

  function updateHiddenData() {
    if (fieldsDataInput instanceof HTMLInputElement) {
      fieldsDataInput.value = JSON.stringify(fields);
    }
    syncLogoPath();
  }

  function bindDragAndDrop() {
    canvas.querySelectorAll('[data-field-index]').forEach((el) => {
      el.addEventListener('dragstart', (event) => {
        dragFromIndex = Number(el.getAttribute('data-field-index'));
        el.classList.add('opacity-60', 'border-green-500');
        event.dataTransfer?.setData('text/plain', String(dragFromIndex));
        event.dataTransfer.effectAllowed = 'move';
      });

      el.addEventListener('dragend', () => {
        el.classList.remove('opacity-60', 'border-green-500');
        dragFromIndex = null;
      });

      el.addEventListener('dragover', (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        el.classList.add('border-green-500');
      });

      el.addEventListener('dragleave', () => {
        el.classList.remove('border-green-500');
      });

      el.addEventListener('drop', (event) => {
        event.preventDefault();
        el.classList.remove('border-green-500');
        const toIndex = Number(el.getAttribute('data-field-index'));
        const fromIndex = dragFromIndex ?? Number(event.dataTransfer?.getData('text/plain'));
        if (Number.isNaN(fromIndex) || Number.isNaN(toIndex) || fromIndex === toIndex) return;
        const [item] = fields.splice(fromIndex, 1);
        fields.splice(toIndex, 0, item);
        renderCanvas();
      });
    });
  }

  function bindLogoUploads() {
    canvas.querySelectorAll('[data-logo-upload]').forEach((input) => {
      if (!(input instanceof HTMLInputElement)) return;
      const index = Number(input.getAttribute('data-logo-upload'));

      input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;
        await uploadLogoFile(index, file);
      });

      const dropZone = input.closest('label');
      if (!(dropZone instanceof HTMLElement)) return;

      ['dragenter', 'dragover'].forEach((type) => {
        dropZone.addEventListener(type, (event) => {
          event.preventDefault();
          dropZone.classList.add('ring-1', 'ring-green-500');
        });
      });
      ['dragleave', 'drop'].forEach((type) => {
        dropZone.addEventListener(type, (event) => {
          event.preventDefault();
          dropZone.classList.remove('ring-1', 'ring-green-500');
        });
      });
      dropZone.addEventListener('drop', async (event) => {
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;
        await uploadLogoFile(index, file);
      });
    });
  }

  async function uploadLogoFile(index, file) {
    const status = canvas.querySelector(`[data-logo-status="${index}"]`);
    if (status) status.textContent = 'Uploading…';

    const body = new FormData();
    body.append('logo', file);

    try {
      const response = await fetch(uploadLogoUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body,
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = payload.message || payload.errors?.logo?.[0] || 'Upload failed';
        if (status) status.textContent = message;
        return;
      }

      if (fields[index]) {
        fields[index].image_path = payload.path;
        fields[index].image_url = payload.url || storageUrl(payload.path);
      }
      if (logoPathInput instanceof HTMLInputElement) {
        logoPathInput.value = payload.path || '';
      }
      renderCanvas();
    } catch {
      if (status) status.textContent = 'Upload failed';
    }
  }

  document.querySelectorAll('[data-add-field]').forEach((btn) => {
    btn.addEventListener('click', () => addField(btn.dataset.addField));
  });

  function setActivateState(active) {
    const input = document.getElementById('activate-input');
    const toggle = document.querySelector('[data-activate-toggle]');
    if (!(input instanceof HTMLInputElement)) return;

    input.value = active ? '1' : '0';
    if (!(toggle instanceof HTMLElement)) return;

    toggle.setAttribute('aria-checked', active ? 'true' : 'false');
    toggle.classList.toggle('bg-green-500', active);
    toggle.classList.toggle('bg-green-50', !active);
    const knob = toggle.querySelector('span');
    if (knob instanceof HTMLElement) {
      knob.classList.toggle('left-[24px]', active);
      knob.classList.toggle('left-[2px]', !active);
    }
  }

  formEl?.addEventListener('submit', (event) => {
    updateHiddenData();

    if (formEl.dataset.activatePromptBypass === 'true') {
      return;
    }

    const activateInput = document.getElementById('activate-input');
    if (!(activateInput instanceof HTMLInputElement) || activateInput.value === '1') {
      return;
    }

    if (window.WapAppFormValidation && !window.WapAppFormValidation.validateForm(formEl)) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const cancelBtn = document.querySelector('#app-confirm-dialog [data-confirm-cancel]');
    const previousCancelLabel = cancelBtn?.textContent;
    if (cancelBtn) {
      cancelBtn.textContent = 'No, just save';
    }

    const ask = typeof window.showAppConfirm === 'function'
      ? window.showAppConfirm({
          title: 'Activate form?',
          message: 'Do you also want to activate this form?',
          variant: 'default',
          confirmLabel: 'Yes, Activate',
        })
      : Promise.resolve(window.confirm('Do you also want to activate this form?'));

    ask.then((shouldActivate) => {
      if (cancelBtn && previousCancelLabel != null) {
        cancelBtn.textContent = previousCancelLabel;
      }
      setActivateState(Boolean(shouldActivate));
      formEl.dataset.activatePromptBypass = 'true';
      if (typeof formEl.requestSubmit === 'function') {
        formEl.requestSubmit();
      } else {
        formEl.submit();
      }
      delete formEl.dataset.activatePromptBypass;
    });
  });

  (function bindActivateToggle() {
    const input = document.getElementById('activate-input');
    const toggle = document.querySelector('[data-activate-toggle]');
    if (!input || !(toggle instanceof HTMLElement)) return;

    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      setActivateState(input.value !== '1');
    });
  })();

  window.FormBuilderCanvas = {
    addField,
    removeField,
    moveField,
    updateField,
    updateOption,
    addOption,
    removeOption,
  };

  ensureDefaults();
  renderCanvas();
})();
</script>
