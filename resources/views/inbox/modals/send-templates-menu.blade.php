@php
    $freeCreateUrl = route('templates.free.create');
    $savedMessageTypes = [
        ['id' => 'tap-to-reply', 'title' => 'Tap-to-Reply Buttons', 'desc' => 'Let your customer tap a quick button instead of typing.', 'image' => 'tap-to-reply.png'],
        ['id' => 'list-of-choices', 'title' => 'List of Choices', 'desc' => 'Show many options in one clean list. Customer just picks one.', 'image' => 'list-of-choices.png'],
        ['id' => 'share-one-product', 'title' => 'Share One Product', 'desc' => 'Show one product nicely in a card,', 'image' => 'share-one-product.png'],
        ['id' => 'share-many-products', 'title' => 'Share Many Products', 'desc' => 'Show multiple products together so your customer can browse.', 'image' => 'share-many-products.png'],
        ['id' => 'share-store', 'title' => 'Share Your Store', 'desc' => 'Send your full catalog so the customer can explore everything.', 'image' => 'share-store.png'],
        ['id' => 'website-button', 'title' => 'Website Button', 'desc' => 'Add a button that opens your website or any link.', 'image' => 'website-button.png'],
        ['id' => 'ask-few-questions', 'title' => 'Ask a Few Questions', 'desc' => 'Send a tiny form—great for getting quick info.', 'image' => 'ask-questions.png'],
        ['id' => 'ask-for-location', 'title' => 'Ask for Location', 'desc' => 'Customer can share their live location with one tap.', 'image' => 'ask-location.png'],
        ['id' => 'ask-for-address', 'title' => 'Ask for Address', 'desc' => 'Let the customer send their full address easily.', 'image' => 'ask-address.png'],
    ];
    $modal = config('inbox-modals.send-templates-menu');
@endphp

<x-inbox.modal id="send-templates-menu" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="true" :open="$open ?? false">
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5 pt-0">
        {{-- Send Saved Free Template --}}
        <form class="mt-3 flex flex-col gap-8 rounded-xl border border-border-light bg-muted-surface p-4" data-inbox-interactive-form>
            <div class="flex flex-col gap-2">
                <p class="fd-label text-sm">Send Saved Message</p>
                <select
                    name="interactive_message_id"
                    data-inbox-interactive-select
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    required
                >
                    <option value="">Loading saved messages...</option>
                </select>
                <p class="text-xs text-text-muted" style="font-family: var(--font-display)">
                    Choose a Free Template (button / list / product / flow) saved under Templates → Free Templates.
                </p>
                <p class="hidden text-xs text-red-500" data-inbox-interactive-error></p>
            </div>
            <div class="flex items-center justify-between border-t border-[rgba(90,90,90,0.15)] py-4">
                <button type="button" data-modal-close class="fd-btn rounded-lg border border-green-500 px-6 py-3 text-base text-green-500 hover:bg-green-50">Cancel</button>
                <button type="submit" class="fd-btn rounded-lg bg-green-500 px-6 py-3 text-base text-primary-2 hover:opacity-90">Send</button>
            </div>
        </form>

        {{-- Create New — deep-link to Free Template builder (shell modals are not wired) --}}
        <div class="flex flex-col gap-4 bg-muted-surface p-4">
            <h3 class="fd-section-title text-2xl">Create New</h3>
            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($savedMessageTypes as $type)
                    <a
                        href="{{ $freeCreateUrl }}"
                        class="relative flex h-[131px] flex-col gap-5 overflow-hidden rounded-2xl border border-[#eee] bg-elevated p-6 text-left transition-colors hover:border-green-500"
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
                    </a>
                @endforeach
            </div>
            <p class="text-xs text-text-muted" style="font-family: var(--font-display)">
                Tip: build reusable messages under
                <a href="{{ $freeCreateUrl }}" class="text-green-500 underline">Templates → Free Templates</a>,
                then send them from “Send Saved Message” above.
            </p>
        </div>
    </div>
</x-inbox.modal>
