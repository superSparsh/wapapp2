<x-layouts.guest title="Multi-Product - WapApp">
<x-automation.modal-layout title="Share Many Products" subtitle="Share multiple products in a single message.">
  <x-inbox.modal-form>
    <form class="flex flex-col gap-6">
      <x-form.input id="catalog_header" placeholder="Our Products" required>
        <x-slot:label>Header <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>

      <x-form.input id="catalog_body" placeholder="Browse our catalog" required>
        <x-slot:label>Body <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>

      <div class="flex flex-col gap-2">
        <x-form.label>Select Products <span class="text-red-500">*</span></x-form.label>
        @foreach (['Premium Plan - ₹999', 'Starter Kit - ₹2,499', 'Consultation - ₹1,500'] as $product)
          <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-elevated p-3 hover:border-green-500">
            <input type="checkbox" class="size-4 rounded accent-[#6dbb48]" checked>
            <span class="text-sm font-medium text-text-primary" style="font-family: var(--font-display)">{{ $product }}</span>
          </label>
        @endforeach
      </div>

      <x-inbox.modal-actions submit="Send Product Catalog" />
    </form>
  </x-inbox.modal-form>
  <x-inbox.phone-preview />
</x-automation.modal-layout>
</x-layouts.guest>
