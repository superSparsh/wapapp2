@php
    $savedMessageTypes = [
        ['id' => 'tap-to-reply', 'title' => 'Tap-to-Reply Buttons', 'desc' => 'Let your customer tap a quick button instead of typing.', 'image' => 'tap-to-reply.png', 'active' => true],
        ['id' => 'list-of-choices', 'title' => 'List of Choices', 'desc' => 'Show many options in one clean list. Customer just picks one.', 'image' => 'list-of-choices.png', 'active' => false],
        ['id' => 'share-one-product', 'title' => 'Share One Product', 'desc' => 'Show one product nicely in a card,', 'image' => 'share-one-product.png', 'active' => false],
        ['id' => 'share-many-products', 'title' => 'Share Many Products', 'desc' => 'Show multiple products together so your customer can browse.', 'image' => 'share-many-products.png', 'active' => false],
        ['id' => 'share-many-products', 'title' => 'Share Your Store', 'desc' => 'Send your full catalog so the customer can explore everything.', 'image' => 'share-store.png', 'active' => false],
        ['id' => 'website-button', 'title' => 'Website Button', 'desc' => 'Add a button that opens your website or any link.', 'image' => 'website-button.png', 'active' => false],
        ['id' => 'ask-few-questions', 'title' => 'Ask a Few Questions', 'desc' => 'Send a tiny form—great for getting quick info.', 'image' => 'ask-questions.png', 'active' => false],
        ['id' => 'ask-for-location', 'title' => 'Ask for Location', 'desc' => 'Customer can share their live location with one tap.', 'image' => 'ask-location.png', 'active' => false],
        ['id' => 'ask-for-address', 'title' => 'Ask for Address', 'desc' => 'Let the customer send their full address easily.', 'image' => 'ask-address.png', 'active' => false],
    ];
    $modal = config('inbox-modals.send-templates-menu');
@endphp

<x-inbox.modal id="send-templates-menu" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="true" :open="$open ?? false">
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5 pt-0">
        {{-- Send Saved Message --}}
        <div class="flex flex-col gap-8 mt-3 rounded-xl border border-border-light bg-muted-surface p-4">
            <div class="flex flex-col gap-2">
                <p class="fd-label text-sm">Send Saved Message</p>
                <div class="flex items-center gap-3 rounded-xl border border-border bg-elevated p-3.5">
                    <span class="fd-filter-placeholder min-w-0 flex-1 text-sm">Select a Send Saved Message</span>
                    <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
                </div>
            </div>
            <div class="flex items-center justify-between border-t border-[rgba(90,90,90,0.15)] py-4">
                <button type="button" data-modal-close class="fd-btn rounded-lg border border-green-500 px-6 py-3 text-base text-green-500 hover:bg-green-50">Cancel</button>
                <button type="button" class="fd-btn rounded-lg bg-green-500 px-6 py-3 text-base text-primary-2 hover:opacity-90">Send</button>
            </div>
        </div>

        {{-- Create New --}}
        <div class="flex flex-col gap-4 bg-muted-surface p-4">
            <h3 class="fd-section-title text-2xl">Create New</h3>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($savedMessageTypes as $type)
                    <button
                        type="button"
                        data-open-modal="{{ $type['id'] }}"
                        data-close-modal="send-templates-menu"
                        @class([
                            'relative flex h-[131px] flex-col gap-5 overflow-hidden rounded-2xl border p-6 text-left transition-colors',
                            'border-green-500 bg-green-50' => $type['active'],
                            'border-[#eee] bg-elevated hover:border-green-500' => ! $type['active'],
                        ])
                    >
                        <img src="{{ asset('images/inbox/cards/card-ellipse.svg') }}" alt="" class="absolute -right-7 top-[65px] size-[100px]" aria-hidden="true">
                        <img
                            src="{{ asset('images/inbox/cards/' . $type['image']) }}"
                            alt=""
                            class="absolute bottom-[7px] right-[7px] size-12 object-cover"
                            width="48"
                            height="48"
                        >
                        <div class="relative z-10 flex w-full items-center justify-between gap-2">
                            <p class="fd-card-title text-xl text-text-body">{{ $type['title'] }}</p>
                            <img src="{{ asset('images/icons/arrow-right-linear.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
                        </div>
                        <p class="relative z-10 max-w-[280px] text-sm font-medium leading-[1.4] text-text-muted" style="font-family: var(--font-display)">{{ $type['desc'] }}</p>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</x-inbox.modal>
