@php $modal = config('inbox-modals.share-store'); @endphp
<x-inbox.modal id="share-store" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="catalog_message">
            <input type="hidden" name="type" value="catalog_message">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="3" maxlength="1024" required placeholder="Browse our full catalog" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Thumbnail Product ID (Optional)</x-form.label>
                <input type="text" name="product_retailer_id" maxlength="120" placeholder="SKU shown on the catalog card" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Catalog" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
