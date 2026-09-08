<x-layouts.guest title="List Message - WapApp">
<x-automation.modal-layout title="List of Choices" subtitle="Show many options in one clean list. Customer just picks one.">
  <x-inbox.modal-form>
    <form class="flex flex-col gap-8">
      <x-form.input id="list_header" placeholder="Header Text" required>
        <x-slot:label>Header Text <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>

      <div class="flex flex-col gap-2">
        <x-form.label>Body Text <span class="text-red-500">*</span></x-form.label>
        <textarea rows="5" class="w-full rounded-xl border border-border bg-elevated p-3 text-sm leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" style="font-family: var(--font-display)">Hello (first name) {(1)}

I am founder of ABC (Email ID){(2)}</textarea>
      </div>

      <x-form.input id="list_footer" placeholder="Campaign Name">
        <x-slot:label>Footer Text (Optional)</x-slot:label>
      </x-form.input>

      <div class="space-y-4 rounded-[20px] bg-elevated p-5">
        <h3 class="text-xl font-semibold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">Sections</h3>
        @foreach (range(1, 2) as $section)
          <div class="space-y-8 rounded-xl border border-border-light bg-muted-surface p-4">
            <div class="flex items-end gap-2">
              <x-form.input id="section_{{ $section }}" placeholder="Section Title" class="flex-1">
                <x-slot:label>Section {{ $section }} Title</x-slot:label>
              </x-form.input>
              <button type="button" class="mb-3.5 flex size-12 shrink-0 items-center justify-center" aria-label="Remove section">
                <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-5" width="20" height="20">
              </button>
            </div>
            <div class="space-y-2">
              <p class="text-sm font-bold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">Rows (1–10 per section)</p>
              <div class="flex items-end gap-2">
                <x-form.input id="row_id_{{ $section }}" placeholder="Row ID" class="flex-1">
                  <x-slot:label>Row ID<span class="text-red-500">*</span></x-slot:label>
                </x-form.input>
                <x-form.input id="row_title_{{ $section }}" placeholder="Row Title" class="flex-1">
                  <x-slot:label>Row Tittle</x-slot:label>
                </x-form.input>
                <button type="button" class="mb-3.5 flex size-12 shrink-0 items-center justify-center" aria-label="Remove row">
                  <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </div>
              <textarea rows="3" placeholder="Raw description (optional, max 72 cnars)" class="w-full rounded-xl border border-border bg-elevated p-3 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" style="font-family: var(--font-display)"></textarea>
              <button type="button" class="w-full text-right text-sm font-medium text-green-500 underline" style="font-family: var(--font-display)">Add Row</button>
            </div>
          </div>
        @endforeach
        <div class="flex justify-end">
          <button type="button" class="rounded bg-green-500 px-6 py-3 text-sm font-semibold leading-[1.5] text-primary-2" style="font-family: var(--font-display)">Add Section</button>
        </div>
      </div>

      <x-inbox.modal-actions submit="Create List Message" />
    </form>
  </x-inbox.modal-form>
  <x-inbox.phone-preview />
</x-automation.modal-layout>
</x-layouts.guest>
