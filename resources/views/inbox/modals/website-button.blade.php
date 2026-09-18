@php $modal = config('inbox-modals.website-button'); @endphp
<x-inbox.modal id="website-button" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="cta_url">
            <input type="hidden" name="type" value="cta_url">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="3" maxlength="1024" required placeholder="Check out our store" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Button Text <span class="text-red-500">*</span></x-form.label>
                <input type="text" name="button_text" maxlength="20" required placeholder="Shop Now" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>URL <span class="text-red-500">*</span></x-form.label>
                <input type="url" name="url" maxlength="2000" required placeholder="https://example.com" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Website Button" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
