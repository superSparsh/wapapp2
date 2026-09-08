@php
  $conditionRows = [1, 2];
@endphp

<div id="modal-create-segment" data-modal="create-segment" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-create-segment">
  <div class="flex max-h-[90vh] w-full max-w-[660px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-create-segment" class="text-2xl font-bold leading-[1.5] text-text-primary">Create segment</h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Create segment</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form method="POST" action="{{ route('audience.segments.store') }}" class="space-y-4">
      @csrf
      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-4 md:flex-row">
            <div class="min-w-0 flex-1">
              <label for="segment_name" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                Segment Name <span class="text-red-500">*</span>
              </label>
              <input
                id="segment_name"
                name="name"
                type="text"
                required
                placeholder="Enter Segment Name"
                class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
            </div>

            <div class="min-w-0 flex-1">
              <label for="segment_condition_type" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                How to combine the conditions * <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <select id="segment_condition_type" class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                  <option selected>All</option>
                  <option>Any</option>
                </select>
                <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <p class="text-[28px] font-bold leading-[1.2] text-text-primary md:text-[16px]">Conditions</p>

            @foreach ($conditionRows as $row)
              <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">
                    List Fields<span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                      <option selected>Select List Fields</option>
                    </select>
                    <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                  </div>
                </div>

                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">
                    Select <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                      <option selected>Equal</option>
                    </select>
                    <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                  </div>
                </div>

                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">
                    ... <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                      <option selected>....</option>
                    </select>
                    <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                  </div>
                </div>

                <button type="button" class="flex h-[48px] w-[48px] items-center justify-center self-stretch lg:self-auto" aria-label="Remove condition">
                  <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </div>
            @endforeach
          </div>

          <div>
            <button type="button" class="fd-btn rounded border border-green-500 px-6 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">
              Add Condition
            </button>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
          Save
        </button>
      </div>
    </form>
  </div>
</div>
