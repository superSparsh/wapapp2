@php $modal = config('inbox-modals.share-one-product'); @endphp
<x-inbox.modal id="share-one-product" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-8">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body (Optional)</x-form.label>
                <input
                    type="text"
                    id="product_message_body"
                    placeholder="Description text"
                    class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    style="font-family: var(--font-display)"
                >
            </div>

            <div class="flex flex-col gap-2">
                <x-form.label>Catalog <span class="text-red-500">*</span></x-form.label>
                <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                    <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">Select a catalog</span>
                    <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <x-form.label>Product <span class="text-red-500">*</span></x-form.label>
                <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                    <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">Select a Product</span>
                    <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                </div>
            </div>

            <x-inbox.modal-actions submit="Create Product Message" />
        </form>
    </x-inbox.modal-form>

    <x-inbox.phone-preview title="Message Preview" subtitle="Template preview message look like">
        <x-slot:preview>
            <div class="mx-auto w-full max-w-[354px] rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-border bg-elevated p-2">
                <img
                    src="{{ asset('images/inbox/modals/tap-reply-header.png') }}"
                    alt=""
                    class="aspect-[1600/800] w-full rounded object-cover"
                    width="338"
                    height="169"
                >
                <p class="mt-3 whitespace-pre-wrap text-base font-normal leading-[1.4] text-text-body" style="font-family: var(--font-display)">Hello Name (text) {(1)}

I am founder of ABC (Email){(2)}

Thanks for reaching out</p>
                <img src="{{ asset('images/inbox/modals/vector-divider.svg') }}" alt="" class="my-3 block w-full" width="338" height="1">
                <div class="flex items-center justify-center gap-2 py-1">
                    <img src="{{ asset('images/inbox/modals/call.svg') }}" alt="" class="size-4" width="16" height="16">
                    <span class="text-base font-medium leading-[1.4] text-link-green" style="font-family: var(--font-display)">Call Us</span>
                </div>
                <div class="flex items-center justify-center gap-2 py-1">
                    <img src="{{ asset('images/inbox/modals/export.svg') }}" alt="" class="size-4" width="16" height="16">
                    <span class="text-base font-medium leading-[1.4] text-link-green" style="font-family: var(--font-display)">Visit Our Website</span>
                </div>
                <div class="flex items-center justify-center gap-2 py-1">
                    <img src="{{ asset('images/inbox/modals/export.svg') }}" alt="" class="size-4" width="16" height="16">
                    <span class="text-base font-medium leading-[1.4] text-link-green" style="font-family: var(--font-display)">Order Now</span>
                </div>
            </div>
        </x-slot:preview>
    </x-inbox.phone-preview>
</x-inbox.modal>
