<x-layouts.guest title="Single Product - WapApp">
<x-automation.modal-layout title="Share One Product" subtitle="Share a single product with your customer.">
  <x-inbox.modal-form>
    <form class="flex flex-col gap-6">
      <div class="flex flex-col gap-2">
        <x-form.label>Select Product <span class="text-red-500">*</span></x-form.label>
        <select class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" style="font-family: var(--font-display)">
          <option>Choose a product</option>
          <option>Premium Plan - ₹999/mo</option>
          <option>Starter Kit - ₹2,499</option>
          <option>Consultation - ₹1,500</option>
        </select>
      </div>

      <div class="rounded-xl border border-border-light bg-elevated p-4">
        <div class="flex gap-4">
          <div class="flex size-20 shrink-0 items-center justify-center rounded-lg bg-green-50 text-2xl">📦</div>
          <div>
            <p class="text-base font-semibold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">Premium Plan</p>
            <p class="text-sm font-medium text-green-500" style="font-family: var(--font-display)">₹ 999/month</p>
            <p class="mt-1 text-xs leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">Unlimited messages, API access, priority support</p>
          </div>
        </div>
      </div>

      <x-inbox.modal-actions submit="Send Product" />
    </form>
  </x-inbox.modal-form>
  <x-inbox.phone-preview />
</x-automation.modal-layout>
</x-layouts.guest>
