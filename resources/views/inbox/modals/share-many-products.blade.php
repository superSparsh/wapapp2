@php $modal = config('inbox-modals.share-many-products'); @endphp
<x-inbox.modal id="share-many-products" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-8">
            {{-- Header Text --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Header Text <span class="text-red-500">*</span></x-form.label>
                <input
                    type="text"
                    id="multi_header"
                    placeholder="Header Text"
                    class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    style="font-family: var(--font-display)"
                >
            </div>

            {{-- Body Text --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Body Text <span class="text-red-500">*</span></x-form.label>
                <div class="relative rounded-xl border border-border bg-elevated p-3">
                    <textarea
                        rows="4"
                        class="w-full resize-none bg-transparent text-sm font-normal leading-[1.4] text-text-body placeholder:text-text-muted focus:outline-none"
                        style="font-family: var(--font-display)"
                        placeholder="Hello (first name) {(1)}"
                    >Hello (first name) {(1)}

I am founder of ABC (Email ID){(2)}</textarea>
                    <img
                        src="{{ asset('images/inbox/modals/rich-text-toolbar-crop.png') }}"
                        alt=""
                        class="mt-8 h-[30px] w-[250px] object-contain object-left"
                        width="250"
                        height="30"
                    >
                </div>
            </div>

            {{-- Catalog --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Catalog <span class="text-red-500">*</span></x-form.label>
                <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                    <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">Select a catalog</span>
                    <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                </div>
            </div>

            {{-- Product Sections --}}
            <div class="flex w-full flex-col gap-4 rounded-[20px] bg-elevated p-5">
                <h3 class="text-xl font-semibold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">Product Sections</h3>

                <div class="flex w-full flex-col gap-8 overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
                    <div class="flex items-start gap-2">
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <x-form.label>Section Title</x-form.label>
                            <input
                                type="text"
                                id="product_section_title"
                                placeholder="Section Title"
                                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                                style="font-family: var(--font-display)"
                            >
                        </div>
                        <div class="flex h-[76px] w-12 shrink-0 items-end justify-center pb-3.5">
                            <button type="button" aria-label="Remove section">
                                <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-bold leading-[1.4] text-text-primary" style="font-family: var(--font-display)">Products</p>

                        <div class="flex items-start gap-2">
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <x-form.label>Select product <span class="text-red-500">*</span></x-form.label>
                                <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                                    <span class="min-w-0 flex-1 text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">Product name</span>
                                    <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                                </div>
                            </div>
                            <div class="flex h-[76px] w-12 shrink-0 items-end justify-center pb-3.5">
                                <button type="button" aria-label="Remove product">
                                    <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                                </button>
                            </div>
                        </div>

                        <button type="button" class="text-right text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">
                            Add Product
                        </button>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="fd-btn rounded bg-green-500 px-6 py-3 text-sm text-primary-2 hover:opacity-90">
                        Add Another Section
                    </button>
                </div>
            </div>

            <x-inbox.modal-actions submit="Create Multi-Product Message" />
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
