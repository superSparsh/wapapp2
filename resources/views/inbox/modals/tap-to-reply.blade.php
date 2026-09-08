@php $modal = config('inbox-modals.tap-to-reply'); @endphp
<x-inbox.modal id="tap-to-reply" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-8">
            {{-- Header Type (open dropdown as in Figma) --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Header Type <span class="text-red-500">*</span></x-form.label>
                <div class="overflow-hidden rounded-xl border border-border bg-elevated">
                    <div class="flex h-12 items-center gap-4 border border-border px-4">
                        <span class="flex-1 text-sm font-medium leading-[1.5] text-text-muted" style="font-family: var(--font-display)">Image</span>
                        <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                    </div>
                    <div class="flex h-12 items-center border border-border px-4">
                        <span class="text-sm font-medium leading-[1.5] text-text-muted" style="font-family: var(--font-display)">Text</span>
                    </div>
                    <div class="flex h-12 items-center border border-border bg-green-50 px-4">
                        <span class="text-sm font-medium leading-[1.5] text-green-500" style="font-family: var(--font-display)">Image</span>
                    </div>
                    <div class="flex h-12 items-center border border-border px-4">
                        <span class="text-sm font-medium leading-[1.5] text-text-muted" style="font-family: var(--font-display)">Video</span>
                    </div>
                    <div class="flex h-12 items-center border border-border px-4">
                        <span class="text-sm font-medium leading-[1.5] text-text-muted" style="font-family: var(--font-display)">Document</span>
                    </div>
                </div>
            </div>

            {{-- Upload zone --}}
            <div class="flex h-[88px] flex-col items-center justify-center rounded-md border border-dashed border-divider bg-elevated px-4 py-3">
                <img src="{{ asset('images/inbox/modals/upload-file.svg') }}" alt="" class="size-6" width="24" height="24">
                <p class="mt-2 text-center text-xs font-medium text-text-body" style="font-family: var(--font-display)">
                    Drag &amp; Drop or <span class="text-green-500">choose</span> file to upload
                </p>
                <p class="mt-1 text-center text-[10px] font-medium text-text-body opacity-50" style="font-family: var(--font-display)">
                    Only Support .png or .jpg
                </p>
            </div>

            {{-- Selected image --}}
            <div class="flex w-fit flex-col gap-2">
                <x-form.label>Image</x-form.label>
                <div class="inline-flex flex-col gap-2 rounded-xl border border-border bg-elevated px-3 pb-2 pt-3">
                    <img
                        src="{{ asset('images/inbox/modals/tap-reply-header.png') }}"
                        alt="Header image"
                        class="h-20 w-40 rounded object-cover"
                        width="160"
                        height="80"
                    >
                    <div class="flex w-full items-center justify-between">
                        <button type="button" class="text-[10px] font-semibold leading-[1.4] text-primary-2 underline" style="font-family: var(--font-display)">Change</button>
                        <button type="button" aria-label="Remove image">
                            <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                    </div>
                </div>
            </div>

            {{-- Message Body --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
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

            {{-- Footer Text --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Footer Text (Optional)</x-form.label>
                <input
                    type="text"
                    id="footer_text"
                    placeholder="Campaign Name"
                    class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    style="font-family: var(--font-display)"
                >
            </div>

            {{-- Buttons --}}
            <div class="flex flex-col gap-2">
                <x-form.label>Buttons (1-3 allowed)</x-form.label>
                @foreach ([1, 2] as $btn)
                    <div class="flex items-center gap-2 rounded-lg bg-elevated p-2">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <x-form.label>Button ID <span class="text-red-500">*</span></x-form.label>
                            <input
                                type="text"
                                id="btn_{{ $btn }}_id"
                                placeholder="Enter Button ID"
                                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                                style="font-family: var(--font-display)"
                            >
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <x-form.label>Button text <span class="text-red-500">*</span></x-form.label>
                            <input
                                type="text"
                                id="btn_{{ $btn }}_text"
                                placeholder="Enter Button text"
                                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                                style="font-family: var(--font-display)"
                            >
                        </div>
                        <div class="flex w-12 shrink-0 items-end justify-center self-stretch pb-3.5">
                            <button type="button" aria-label="Remove button">
                                <img src="{{ asset('images/inbox/modals/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                            </button>
                        </div>
                    </div>
                @endforeach
                <button type="button" class="text-right text-sm font-medium leading-[1.4] text-green-500 underline" style="font-family: var(--font-display)">
                    Add Button
                </button>
            </div>

            <x-inbox.modal-actions submit="Create Interactive Button Message" />
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
