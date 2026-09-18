@php $modal = config('inbox-modals.share-one-product'); @endphp
<x-inbox.modal id="share-one-product" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="product">
            <input type="hidden" name="type" value="product">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body (Optional)</x-form.label>
                <input type="text" name="body" maxlength="1024" placeholder="Take a look at this product" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Catalog ID <span class="text-red-500">*</span></x-form.label>
                <input type="text" name="catalog_id" maxlength="120" required placeholder="WhatsApp catalog ID" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Product Retailer ID <span class="text-red-500">*</span></x-form.label>
                <input type="text" name="product_retailer_id" maxlength="120" required placeholder="SKU / retailer ID" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Product" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
