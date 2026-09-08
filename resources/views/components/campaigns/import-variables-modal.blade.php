@props([
    'customVariableNames' => [],
])

@php
  $sampleColumns = array_merge(['whatsapp_number'], array_values($customVariableNames));
@endphp

<div
  id="modal-import-campaign-variables"
  data-modal="import-campaign-variables"
  class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4"
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-import-campaign-variables"
>
  <div class="flex max-h-[90vh] w-full max-w-[681px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-import-campaign-variables" class="text-2xl font-bold leading-[1.5] text-text-primary">Import Variable Values</h2>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form data-campaign-variables-import-form enctype="multipart/form-data" class="space-y-4">
      @csrf
      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-2">
          <label for="campaign_variable_import_file" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Choose a file to upload
          </label>

          <div class="flex flex-col items-center justify-center rounded-[6px] border border-dashed border-border bg-elevated px-[68px] py-6 text-center transition-colors duration-150 cursor-pointer" data-dropzone>
            <div class="flex w-[206px] flex-col items-center gap-2" data-dropzone-content>
              <img src="{{ asset('images/inbox/modals/upload-file.svg') }}" alt="" class="size-6" width="24" height="24">
              <div class="w-full text-center">
                <p class="text-xs font-medium text-text-body">
                  Drag &amp; Drop or <span class="text-green-500 font-bold">choose</span> file to upload
                </p>
                <p class="mt-1 text-[10px] font-medium text-text-body opacity-50">Only CSV / TXT files (max 2MB)</p>
              </div>
            </div>
            <div class="hidden flex-col items-center gap-2" data-dropzone-preview>
              <x-icons.nav-icon name="document-text" class="size-8 text-green-500" />
              <p class="text-xs font-semibold text-green-500" data-filename></p>
              <p class="text-[10px] text-text-body/60" data-filesize></p>
              <button type="button" class="mt-1 text-[10px] text-red-500 hover:underline" data-dropzone-clear>Remove</button>
            </div>
            <input id="campaign_variable_import_file" name="csv_file" type="file" accept=".csv,.txt" class="sr-only" data-dropzone-input required>
          </div>
        </div>
      </div>

      <p class="hidden text-sm text-red-500" data-import-error></p>
      <p class="hidden text-sm text-green-600" data-import-success></p>

      <div class="flex justify-end gap-2">
        <button type="button" data-modal-close class="fd-btn rounded border border-green-500 bg-elevated px-4 py-3 text-sm font-semibold text-green-500">
          Cancel
        </button>
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90" data-import-submit>
          Import
        </button>
      </div>

      <div class="flex gap-3 rounded-[12px] bg-stat-blue/15 p-[14px]">
        <x-icons.nav-icon name="info-circle" class="size-6 shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold leading-[1.4] text-text-body">File Upload Guidelines</p>
          <ul class="mt-[10px] list-disc space-y-2 pl-5 text-sm font-normal leading-[1.4] text-text-muted">
            <li>CSV or TXT only. First column must be <code class="font-mono">whatsapp_number</code> (or <code class="font-mono">phone</code>).</li>
            <li>Do not include <code class="font-mono">first_name</code>, <code class="font-mono">last_name</code>, or <code class="font-mono">full_name</code> columns — those are filled from contacts.</li>
            <li>Remaining columns should match your template variables: <code class="font-mono">{{ implode(', ', $sampleColumns) }}</code>.</li>
            <li>Only phones already in this campaign audience are updated. Missing or unsubscribed numbers are skipped.</li>
          </ul>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const zone = document.querySelector('#modal-import-campaign-variables [data-dropzone]');
  if (!zone) return;
  const input = zone.querySelector('[data-dropzone-input]');
  const content = zone.querySelector('[data-dropzone-content]');
  const preview = zone.querySelector('[data-dropzone-preview]');
  const filenameEl = zone.querySelector('[data-filename]');
  const filesizeEl = zone.querySelector('[data-filesize]');
  const clearBtn = zone.querySelector('[data-dropzone-clear]');
  const MAX_SIZE = 2 * 1024 * 1024;

  zone.addEventListener('click', (e) => {
    if (e.target.closest('[data-dropzone-clear]') || e.target.closest('label') || e.target.closest('button')) return;
    input.click();
  });
  input.addEventListener('change', () => { if (input.files[0]) handleFile(input.files[0]); });
  ['dragenter', 'dragover'].forEach(evt => {
    zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.add('border-green-500', 'bg-green-50'); });
  });
  ['dragleave', 'drop'].forEach(evt => {
    zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('border-green-500', 'bg-green-50'); });
  });
  zone.addEventListener('drop', (e) => { if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); } });
  clearBtn.addEventListener('click', (e) => { e.stopPropagation(); clearFile(); });

  function handleFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['csv', 'txt'].includes(ext)) { alert('Only CSV and TXT files are allowed.'); clearFile(); return; }
    if (file.size > MAX_SIZE) { alert('File size exceeds 2MB limit.'); clearFile(); return; }
    content.classList.add('hidden');
    preview.classList.remove('hidden');
    preview.classList.add('flex');
    filenameEl.textContent = file.name;
    filesizeEl.textContent = formatSize(file.size);
  }
  function clearFile() {
    input.value = '';
    content.classList.remove('hidden');
    preview.classList.add('hidden');
    preview.classList.remove('flex');
  }
  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }
});
</script>
