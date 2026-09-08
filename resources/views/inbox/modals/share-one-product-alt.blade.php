@php $modal = config('inbox-modals.share-one-product-alt'); @endphp
<x-inbox.modal id="share-one-product-alt" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6">
            <x-form.input id="product_name" placeholder="Product name" required>
                <x-slot:label>Product Name <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <x-form.input id="product_price" placeholder="₹ 999" required>
                <x-slot:label>Price <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <div class="flex flex-col gap-2">
                <x-form.label>Description</x-form.label>
                <textarea rows="3" class="w-full rounded-xl border border-border bg-elevated p-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" placeholder="Product description"></textarea>
            </div>
            <div class="flex h-[88px] flex-col items-center justify-center rounded-md border border-dashed border-divider bg-elevated">
                <p class="text-xs"><span class="text-text-muted">Drag & Drop or</span> <span class="text-green-500">choose</span> product image</p>
            </div>
            <x-inbox.modal-actions submit="Send Product" />
        </form>
    </x-inbox.modal-form>
    <x-inbox.phone-preview />
</x-inbox.modal>
