<div id="modal-create-list" data-modal="create-list" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-create-list">
  <div class="flex w-full max-w-[597px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-create-list" class="text-2xl font-bold leading-[1.5] text-text-primary">Create list</h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Adding new subscriber</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form method="POST" action="{{ route('audience.lists.store') }}" data-validate-form class="space-y-4">
      @csrf
      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-2">
          <label for="whatsapp_list_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Create WhatsApp list <x-form.required />
          </label>
          <input
            id="whatsapp_list_name"
            name="name"
            type="text"
            required
            placeholder="Enter WhatsApp list name"
            class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          >
        </div>
      </div>

      <div class="flex items-center justify-between">
        <button type="button" data-modal-close class="fd-btn rounded border border-green-500 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">
          Cancel
        </button>
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
          Create
        </button>
      </div>
    </form>
  </div>
</div>
