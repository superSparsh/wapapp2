@php $modal = config('inbox-modals.share-many-products'); @endphp
<x-inbox.modal id="share-many-products" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="product_list">
            <input type="hidden" name="type" value="product_list">
            <div class="flex flex-col gap-2">
                <x-form.label>Header Text</x-form.label>
                <input type="text" name="header" maxlength="60" placeholder="Our picks" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Body Text <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="3" maxlength="1024" required placeholder="Browse a few products from our catalog" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Catalog ID <span class="text-red-500">*</span></x-form.label>
                <input type="text" name="catalog_id" maxlength="120" required placeholder="WhatsApp catalog ID" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Section Title</x-form.label>
                <input type="text" name="section_title" maxlength="24" placeholder="Products" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Product Retailer IDs <span class="text-red-500">*</span></x-form.label>
                <textarea name="product_retailer_ids_text" rows="4" required placeholder="One SKU per line" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
                <p class="text-xs text-text-muted">Enter one product retailer ID per line (max 10).</p>
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Products" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
