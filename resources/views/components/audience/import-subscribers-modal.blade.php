@props([
    'mailListId' => null,
    'mailListName' => null,
    'mailLists' => null,
])

@php
  $lists = $mailLists ?? collect();
@endphp

<div id="modal-import-subscribers" data-modal="import-subscribers" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-import-subscribers">
  <div class="flex max-h-[90vh] w-full max-w-[681px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-import-subscribers" class="text-2xl font-bold leading-[1.5] text-text-primary">Import subscribers</h2>
        <p class="mt-1 hidden text-sm text-text-muted" data-import-list-label></p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form method="POST" action="{{ route('audience.subscribers.import.store') }}" enctype="multipart/form-data" class="space-y-4" data-import-subscribers-form>
      @csrf
      <input type="hidden" name="mail_list_id" value="{{ $mailListId ?? '' }}" data-import-list-input required>

      @if (! $mailListId && $lists->isNotEmpty())
        <div data-import-list-select-wrap>
          <label for="import_mail_list_id" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
            List <span class="text-red-500">*</span>
          </label>
          <select
            id="import_mail_list_id"
            data-import-list-select
            class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          >
            <option value="">Select a list</option>
            @foreach ($lists as $listOption)
              <option value="{{ $listOption->uuid }}">{{ $listOption->name }}</option>
            @endforeach
          </select>
        </div>
      @endif

      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-2">
          <label for="subscriber_import_file" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Choose a file to upload
          </label>

          <div id="dropzone-modal" class="flex flex-col items-center justify-center rounded-[6px] border border-dashed border-border bg-elevated px-[68px] py-6 text-center transition-colors duration-150 cursor-pointer" data-dropzone>
            <div class="flex w-[206px] flex-col items-center gap-2" data-dropzone-content>
              <img src="{{ asset('images/inbox/modals/upload-file.svg') }}" alt="" class="size-6" width="24" height="24">
              <div class="w-full text-center">
                <p class="text-xs font-medium text-text-body">
                  Drag &amp; Drop or <span class="text-green-500 font-bold">choose</span> file to upload
                </p>
                <p class="mt-1 text-[10px] font-medium text-text-body opacity-50">Only CSV files (max 100MB)</p>
              </div>
            </div>
            <div class="hidden flex-col items-center gap-2" data-dropzone-preview>
              <x-icons.nav-icon name="document-text" class="size-8 text-green-500" />
              <p class="text-xs font-semibold text-green-500" data-filename></p>
              <p class="text-[10px] text-text-body/60" data-filesize></p>
              <button type="button" class="mt-1 text-[10px] text-red-500 hover:underline" data-dropzone-clear>Remove</button>
            </div>
            <input id="subscriber_import_file" name="file" type="file" accept=".csv,.txt" class="sr-only" data-dropzone-input required>
          </div>
        </div>
      </div>

      <label class="flex items-center gap-2 text-sm font-medium text-text-body">
        <input type="hidden" name="send_opt_in_message" value="no">
        <input type="checkbox" name="send_opt_in_message" value="yes" class="size-4 rounded border-border">
        Send WhatsApp opt-in message to all imported contacts (overrides CSV <code>send_opt_in_message</code> column)
      </label>

      <div class="flex justify-end">
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
          Import
        </button>
      </div>

      <div class="flex gap-3 rounded-[12px] bg-stat-blue/15 p-[14px]">
        <x-icons.nav-icon name="info-circle" class="size-6 shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold leading-[1.4] text-text-body">File Upload Guidelines</p>
          <ul class="mt-[10px] list-disc space-y-3 pl-5 text-sm font-normal leading-[1.4] text-text-muted">
            <li><strong>File Size Limit:</strong> Maximum upload size is <strong>100MB</strong>.</li>
            <li><strong>File Type:</strong> CSV only. The first row must be a <strong>header row</strong> with exact column names.</li>
            <li><strong>Required columns:</strong>
              <code>phone_number</code>,
              <code>FIRST_NAME</code>,
              <code>LAST_NAME</code>,
              <code>existing_customer</code>,
              <code>send_opt_in_message</code>.
              (Matched case-insensitively; use the sample file spelling.)
            </li>
            <li><strong>Opt-in fields</strong> (<code>existing_customer</code> / <code>send_opt_in_message</code>):
              Use <code>yes</code> or <code>no</code> (also accepted: y/n, true/false, 1/0).
              Empty cells default to <strong>existing_customer = yes</strong> and <strong>send_opt_in_message = no</strong>.
            </li>
            <li>
              <strong>Sample Input File:</strong>
              <a href="{{ asset('files/csv_import_example.csv') }}" download class="font-semibold underline">Sample.csv</a>
            </li>
          </ul>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modal-import-subscribers');
  if (!modal) return;

  const listInput = modal.querySelector('[data-import-list-input]');
  const listLabel = modal.querySelector('[data-import-list-label]');
  const listSelect = modal.querySelector('[data-import-list-select]');
  const listSelectWrap = modal.querySelector('[data-import-list-select-wrap]');
  const defaultListId = @json($mailListId);
  const defaultListName = @json($mailListName);

  const setTargetList = (listId, listName) => {
    if (listInput && listId) {
      listInput.value = listId;
    }
    if (listLabel) {
      if (listName) {
        listLabel.textContent = 'List: ' + listName;
        listLabel.classList.remove('hidden');
      } else if (listId) {
        listLabel.textContent = '';
        listLabel.classList.add('hidden');
      }
    }
    if (listSelectWrap && listId) {
      listSelectWrap.classList.add('hidden');
    } else if (listSelectWrap && !defaultListId) {
      listSelectWrap.classList.remove('hidden');
    }
    if (listSelect && listId) {
      listSelect.value = listId;
    }
  };

  if (defaultListId) {
    setTargetList(defaultListId, defaultListName);
  }

  document.querySelectorAll('[data-open-modal="import-subscribers"]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const listId = btn.getAttribute('data-mail-list-id') || defaultListId || '';
      const listName = btn.getAttribute('data-mail-list-name') || defaultListName || '';
      setTargetList(listId, listName);
    });
  });

  listSelect?.addEventListener('change', () => {
    const option = listSelect.options[listSelect.selectedIndex];
    setTargetList(listSelect.value, option ? option.textContent : '');
  });

  const form = modal.querySelector('[data-import-subscribers-form]');
  form?.addEventListener('submit', (e) => {
    if (!listInput?.value) {
      e.preventDefault();
      alert('Please select a list before importing.');
    }
  });

  const zone = modal.querySelector('[data-dropzone]');
  if (!zone) return;
  const input = zone.querySelector('[data-dropzone-input]');
  const content = zone.querySelector('[data-dropzone-content]');
  const preview = zone.querySelector('[data-dropzone-preview]');
  const filenameEl = zone.querySelector('[data-filename]');
  const filesizeEl = zone.querySelector('[data-filesize]');
  const clearBtn = zone.querySelector('[data-dropzone-clear]');
  const MAX_SIZE = 100 * 1024 * 1024;

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
    if (file.size > MAX_SIZE) { alert('File size exceeds 100MB limit.'); clearFile(); return; }
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
