<x-layouts.guest title="Buttons Message - WapApp">
<x-automation.modal-layout title="Tap-to-Reply Buttons" subtitle="Let your customer tap a quick button instead of typing.">
  <x-inbox.modal-form>
    <form class="flex flex-col gap-6">
      <div class="flex flex-col gap-2">
        <x-form.label>Header Type <span class="text-red-500">*</span></x-form.label>
        <select class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" style="font-family: var(--font-display)">
          <option>Image</option>
          <option>Text</option>
          <option>Video</option>
          <option>Document</option>
        </select>
      </div>

      <div class="flex h-[88px] flex-col items-center justify-center rounded-md border border-dashed border-divider bg-elevated">
        <p class="text-xs" style="font-family: var(--font-display)"><span class="text-text-muted">Drag & Drop or</span> <span class="text-green-500">choose</span> file</p>
        <p class="text-[10px] text-text-muted/50" style="font-family: var(--font-display)">Only .png or .jpg</p>
      </div>

      <div class="flex flex-col gap-2">
        <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
        <textarea rows="4" class="w-full rounded-xl border border-border bg-elevated p-3 text-sm leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" style="font-family: var(--font-display)">Hello (first name) {(1)}

I am founder of ABC (Email ID){(2)}</textarea>
      </div>

      <x-form.input id="btn_footer" placeholder="Campaign Name">
        <x-slot:label>Footer Text (Optional)</x-slot:label>
      </x-form.input>

      <div class="flex flex-col gap-3">
        <x-form.label>Buttons (1-3 allowed)</x-form.label>
        @foreach (['btn_1', 'btn_2'] as $btn)
          <div class="flex gap-2 rounded-lg bg-elevated p-2">
            <x-form.input :id="$btn.'_id'" placeholder="Button ID" class="flex-1" />
            <x-form.input :id="$btn.'_text'" placeholder="Button text" class="flex-1" />
          </div>
        @endforeach
        <button type="button" class="text-right text-sm font-medium text-green-500 underline" style="font-family: var(--font-display)">Add Button</button>
      </div>

      <x-inbox.modal-actions label="Cancel" submit="Create Interactive Button Message" />
    </form>
  </x-inbox.modal-form>
  <x-inbox.phone-preview />
</x-automation.modal-layout>
</x-layouts.guest>
