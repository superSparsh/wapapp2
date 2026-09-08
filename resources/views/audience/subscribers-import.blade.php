<x-layouts.app title="Import Subscribers - WapApp" active="audience.subscribers">
  <div class="flex flex-col">
    <x-audience.list-header title="Import Subscribers" subscribers="0" />
    <x-audience.sub-nav />

    <div class="p-4">
      <x-ui.page-header title="Import subscribers" subtitle="Upload a CSV file to add contacts to your list.">
        <x-slot:actions>
          <x-ui.link-button href="{{ route('audience.subscribers') }}" variant="outline" size="sm">Back to Subscribers</x-ui.link-button>
        </x-slot:actions>
      </x-ui.page-header>
    </div>

    <section class="p-4 pt-0">
      <form method="POST" action="{{ route('audience.subscribers.import.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="max-w-2xl rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
          <div class="mb-4">
            <x-form.label>Target List</x-form.label>
            <select name="mail_list_id" class="fd-filter-label mt-1.5 w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
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
              <p class="fd-page-note mt-1 text-[10px] text-text-body/70 opacity-100">Required columns: phone, first_name. Max 10,000 rows. Max 100MB.</p>
              <label class="fd-btn mt-6 inline-flex cursor-pointer items-center justify-center rounded-lg bg-green-500 px-3 py-2.5 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
                Choose File
                <input type="file" name="file" accept=".csv,.txt" class="sr-only" data-dropzone-input>
              </label>
              <button type="button" class="fd-btn mt-3 text-[13px] text-green-500 hover:underline" onclick="window.open('https://wapdev.tittu.in/files/csv_import_example-new-5.csv', '_blank')">Download sample CSV</button>
            </div>
            <div class="hidden flex-col items-center gap-2" data-dropzone-preview>
              <x-icons.nav-icon name="document-text" class="size-10 text-green-500" />
              <p class="text-sm font-semibold text-green-500" data-filename></p>
              <p class="text-[11px] text-text-body/60" data-filesize></p>
              <button type="button" class="mt-1 text-xs text-red-500 hover:underline" data-dropzone-clear>Remove file</button>
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
      const MAX_SIZE = 100 * 1024 * 1024; // 100MB

      // Click to browse
      zone.addEventListener('click', (e) => {
        if (e.target.closest('[data-dropzone-clear]') || e.target.closest('label') || e.target.closest('button')) return;
        input.click();
      });

      // File selection
      input.addEventListener('change', () => { if (input.files[0]) handleFile(input.files[0]); });

      // Drag events
      ['dragenter', 'dragover'].forEach(evt => {
        zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.add('border-green-500', 'bg-green-50'); });
      });
      ['dragleave', 'drop'].forEach(evt => {
        zone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('border-green-500', 'bg-green-50'); });
      });
      zone.addEventListener('drop', (e) => { if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); } });

      // Clear
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
        // Mark form as valid
        input.setCustomValidity('');
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
