<x-layouts.app title="Import Subscribers - WapApp" active="audience.subscribers">
  <div class="flex flex-col">
    <x-audience.list-header title="Import Subscribers" subscribers="0" />
    <x-audience.sub-nav />

    <div class="p-4">
      <x-ui.page-header title="Import subscribers" subtitle="Upload a CSV file to add contacts to your list. Large files are processed in the background.">
        <x-slot:actions>
          <x-ui.link-button href="{{ route('audience.subscribers', array_filter(['list' => $mailListId])) }}" variant="outline" size="sm">Back to Subscribers</x-ui.link-button>
        </x-slot:actions>
      </x-ui.page-header>
    </div>

    <section class="p-4 pt-0">
      <form method="POST" action="{{ route('audience.subscribers.import.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="max-w-2xl rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
          @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
          @endif

          <div class="mb-4">
            <x-form.label>Target List <span class="text-red-500">*</span></x-form.label>
            <select name="mail_list_id" required class="fd-filter-label mt-1.5 w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
              <option value="">-- Select List --</option>
              @foreach($mailLists as $list)
                <option value="{{ $list->uuid }}" @selected($mailListId === $list->uuid)>{{ $list->name }}</option>
              @endforeach
            </select>
          </div>

          <div id="dropzone-page" class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-border-light bg-surface p-12 transition-colors duration-150 cursor-pointer" data-dropzone>
            <x-icons.nav-icon name="document-text" class="size-12 text-text-body/40" data-dropzone-icon />
            <div data-dropzone-content>
              <p class="mt-4 text-[13px] font-semibold leading-[1.5] text-text-body">Drag & Drop or <span class="text-green-500 font-bold">choose</span> a CSV file</p>
              <p class="fd-page-note mt-1 text-[10px] text-text-body/70 opacity-100">CSV only, max 100MB. Required headers match Sample.csv.</p>
              <label class="fd-btn mt-6 inline-flex cursor-pointer items-center justify-center rounded-lg bg-green-500 px-3 py-2.5 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
                Choose File
                <input type="file" name="file" accept=".csv,.txt" class="sr-only" data-dropzone-input required>
              </label>
              <a href="{{ asset('files/csv_import_example.csv') }}" download class="fd-btn mt-3 inline-block text-[13px] text-green-500 hover:underline">Download sample CSV</a>
            </div>
            <div class="hidden flex-col items-center gap-2" data-dropzone-preview>
              <x-icons.nav-icon name="document-text" class="size-10 text-green-500" />
              <p class="text-sm font-semibold text-green-500" data-filename></p>
              <p class="text-[11px] text-text-body/60" data-filesize></p>
              <button type="button" class="mt-1 text-xs text-red-500 hover:underline" data-dropzone-clear>Remove file</button>
            </div>
          </div>

          <label class="mt-4 flex items-center gap-2 text-sm font-medium text-text-body">
            <input type="hidden" name="send_opt_in_message" value="no">
            <input type="checkbox" name="send_opt_in_message" value="yes" class="size-4 rounded border-border">
            Send WhatsApp opt-in message to all imported contacts (overrides CSV column)
          </label>

          <div class="mt-4 flex gap-3 rounded-[12px] bg-stat-blue/15 p-[14px]">
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
                </li>
                <li><strong>Opt-in fields:</strong> Use <code>yes</code>/<code>no</code>. Empty cells default to existing_customer=yes and send_opt_in_message=no.</li>
                <li>
                  <strong>Sample:</strong>
                  <a href="{{ asset('files/csv_import_example.csv') }}" download class="font-semibold underline">Sample.csv</a>
                </li>
              </ul>
            </div>
          </div>

          <button type="submit" class="fd-btn mt-6 rounded-lg bg-green-500 px-6 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">Import</button>
        </div>
      </form>
    </section>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      initDropzone(document.getElementById('dropzone-page'));
    });

    function initDropzone(zone) {
      if (!zone) return;
      const input = zone.querySelector('[data-dropzone-input]');
      const content = zone.querySelector('[data-dropzone-content]');
      const preview = zone.querySelector('[data-dropzone-preview]');
      const filenameEl = zone.querySelector('[data-filename]');
      const filesizeEl = zone.querySelector('[data-filesize]');
      const clearBtn = zone.querySelector('[data-dropzone-clear]');
      const MAX_SIZE = 100 * 1024 * 1024;

      zone.addEventListener('click', (e) => {
        if (e.target.closest('[data-dropzone-clear]') || e.target.closest('label') || e.target.closest('button') || e.target.closest('a')) return;
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
    }
  </script>
</x-layouts.app>
