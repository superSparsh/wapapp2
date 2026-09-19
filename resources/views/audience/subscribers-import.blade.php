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
              <a href="{{ asset('files/csv_import_example-new.csv') }}" download class="fd-btn mt-3 inline-block text-[13px] text-green-500 hover:underline">Download sample CSV</a>
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
            Send a WhatsApp opt-in message to everyone in this upload
          </label>

          <div class="mt-4 space-y-3 rounded-[12px] bg-stat-blue/15 p-[14px] text-sm leading-[1.5] text-text-body">
            <div>
              <h6 class="text-sm font-bold text-text-body">File Upload Guidelines</h6>
              <ul class="mt-[10px] list-disc space-y-3 pl-5 font-normal text-text-muted">
                <li>
                  <strong>File Size Limit:</strong> Please be aware that the maximum file size for uploads to our
                  server is <strong>100 megabytes (100MB)</strong>. Make sure that your input file does not exceed
                  this size.
                </li>
                <li>
                  <strong>File Type Requirement:</strong> We accept files in CSV (Comma-Separated Values) format
                  only. The first row must be a <strong>header row</strong> with exact column names.
                </li>
                <li>
                  <strong>Required columns (must all be present in the header row):</strong>
                  <code>country_code</code>,
                  <code>phone_number</code>,
                  <code>FIRST_NAME</code>,
                  <code>LAST_NAME</code>,
                  <code>existing_customer</code>,
                  <code>send_opt_in_message</code>.
                  If any of these headers are missing, the import will fail with an error.
                  (Column names are matched case-insensitively; use the sample file spelling.)
                </li>
                <li>
                  <strong>Opt-in fields (<code>existing_customer</code> / <code>send_opt_in_message</code>):</strong>
                  Use <code>yes</code> or <code>no</code> (also accepted: y/n, true/false, 1/0).
                  If a cell is <strong>empty</strong>, we default to
                  <strong>existing_customer = yes</strong> and <strong>send_opt_in_message = no</strong>
                  (same behaviour as in import processing).
                </li>
                <li>
                  <strong>Sample Input File:</strong> Download a ready-to-use sample:
                  <a href="{{ asset('files/csv_import_example-new.csv') }}" download class="font-semibold text-text-primary underline">Sample.csv</a>.
                  It includes the required headers and example rows.
                </li>
              </ul>
            </div>

            <div class="border-t border-stat-blue/20 pt-3">
              <h6 class="text-sm font-bold text-text-body">Instructions for Saving Excel as CSV</h6>
              <p class="mt-2 font-normal text-text-muted">
                Here are the instructions for saving an Excel file as a CSV for some well-known Operating Systems:
              </p>
              <ul class="mt-2 list-disc space-y-3 pl-5 font-normal text-text-muted">
                <li>
                  <strong>For Windows:</strong>
                  <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>If you are using Microsoft Excel 2019, 2016, 2013, or 2010:</li>
                    <li>Click on the "File" menu at the top left.</li>
                    <li>Choose "Save As" from the menu.</li>
                    <li>Select the location on your computer where you want to save the file.</li>
                    <li>In the "Save As" dialog that appears, look for the dropdown menu labeled "Save as type."</li>
                    <li>From the dropdown menu, choose "CSV (Comma delimited) (*.csv)" as the file type.</li>
                  </ul>
                </li>
                <li>
                  <strong>For Mac:</strong>
                  <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>If you are using Microsoft Excel for Mac:</li>
                    <li>Click on the "File" menu in the upper left corner.</li>
                    <li>Choose "Save As" from the menu.</li>
                    <li>Select the location on your Mac where you want to save the file.</li>
                    <li>In the "Save As" dialog that appears, find the "File Format" dropdown.</li>
                    <li>From the "File Format" dropdown, select "Comma Separated Values (.csv)."</li>
                  </ul>
                </li>
                <li>
                  <strong>For Linux:</strong>
                  <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>Click on "File" in the menu bar.</li>
                    <li>Select "Save As" or "Export."</li>
                    <li>In the "Save As" dialog, choose a location to save the CSV file.</li>
                    <li>In the "File type" dropdown menu, select "Text CSV (*.csv)."</li>
                    <li>Click the "Save" button.</li>
                    <li>You will be presented with a CSV Export Options dialog. Ensure that the options are configured according to your needs and click "OK."</li>
                  </ul>
                </li>
                <li>
                  <strong>Excel Online (the web version of Microsoft Excel):</strong>
                  <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>Click "File" in the upper-left corner.</li>
                    <li>Choose "Save As" and then select "Download."</li>
                    <li>From the list of formats, pick "CSV."</li>
                    <li>Confirm your selection, and the Excel file will download as a CSV.</li>
                  </ul>
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
