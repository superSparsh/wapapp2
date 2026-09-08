<div id="modal-export-subscribers" data-modal="export-subscribers" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-export-subscribers">
  <div class="flex w-full max-w-[597px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-export-subscribers" class="text-2xl font-bold leading-[1.5] text-text-primary">Export subscribers</h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Export subscribers</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form method="POST" action="{{ route('audience.subscribers.export') }}" class="space-y-4">
      @csrf
      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-2">
          <p class="text-sm font-semibold leading-[1.4] text-text-primary">
            Select what do you want to export then click the Export button below to start export.
          </p>

          <div class="flex items-center gap-2" data-radio-option>
            <input type="radio" id="export-whole-list" name="subscriber_export_type" value="whole-list" class="sr-only peer" checked>
            <label for="export-whole-list" class="flex cursor-pointer items-center gap-2 peer-checked:[&_.radio-icon]:opacity-100 peer-checked:[&_.radio-label]:text-green-500">
              <img src="{{ asset('images/templates/radio-checked.svg') }}" alt="" class="size-4 shrink-0 radio-icon opacity-0 transition-opacity" width="16" height="16">
              <span class="text-sm font-medium leading-[1.4] text-text-body radio-label transition-colors">Whole list</span>
            </label>
          </div>

          <div class="flex items-center gap-2" data-radio-option>
            <input type="radio" id="export-filtered" name="subscriber_export_type" value="filtered" class="sr-only peer">
            <label for="export-filtered" class="flex cursor-pointer items-center gap-2 peer-checked:[&_.radio-icon]:opacity-100 peer-checked:[&_.radio-label]:text-green-500">
              <img src="{{ asset('images/templates/radio-checked.svg') }}" alt="" class="size-4 shrink-0 radio-icon opacity-0 transition-opacity" width="16" height="16">
              <span class="text-sm font-medium leading-[1.4] text-text-body radio-label transition-colors">Filtered subscribers</span>
            </label>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
          Export
        </button>
      </div>
    </form>
  </div>
</div>
