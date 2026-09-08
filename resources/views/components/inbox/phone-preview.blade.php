@props([
    'title' => 'Template Preview',
    'subtitle' => 'Template preview message look like',
])

<aside class="hidden w-full shrink-0 flex-col gap-4 overflow-y-auto px-2 py-4 lg:flex lg:w-[441px]">
    <div class="flex flex-col gap-1">
        <h3 class="text-xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">{{ $title }}</h3>
        <p class="text-sm leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">{{ $subtitle }}</p>
    </div>

    <div class="relative mx-auto w-full max-w-[425px]">
        <div class="relative h-[856px] w-full">
            {{-- Phone bezel --}}
            <div class="pointer-events-none absolute inset-[0_3px] rounded-[62px] border border-white/60 shadow-[inset_0px_0px_8px_0px_rgba(0,0,0,0.3)]">
                <div class="absolute inset-0 rounded-[62px] bg-muted-surface"></div>
            </div>
            <div class="absolute inset-[4px_7px] rounded-[58px] bg-black"></div>
            <div class="absolute left-0 top-[136px] h-[30px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute left-0 top-[198px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute left-0 top-[278px] h-[62px] w-[3px] rounded-bl-[1px] rounded-tl-[1px] bg-border shadow-[inset_1px_0px_2px_0px_white]"></div>
            <div class="absolute right-0 top-[220px] h-[100px] w-[3px] rounded-br-[1px] rounded-tr-[1px] bg-border shadow-[inset_-1px_0px_2px_0px_white]"></div>

            {{-- Screen --}}
            <div class="absolute inset-[22px_25px] overflow-hidden rounded-[40px] bg-elevated">
                <img
                    src="{{ asset('images/inbox/modals/phone-screen.png') }}"
                    alt=""
                    class="absolute inset-0 size-full rounded-[8px] object-cover"
                    width="375"
                    height="854"
                >
                <x-ui.phone-preview-header-name size="default" />

                <div class="relative z-10 flex min-h-full flex-col p-3 pt-[100px]">
                    @if (isset($preview) && ! $preview->isEmpty())
                        {{ $preview }}
                    @else
                        <div class="mx-auto w-full max-w-[354px] rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-border bg-elevated p-2">
                            <img
                                src="{{ asset('images/inbox/modals/template-header-image.png') }}"
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
                    @endif
                </div>
            </div>
        </div>
    </div>
</aside>
