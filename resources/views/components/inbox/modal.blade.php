@props(['id', 'title', 'subtitle' => null, 'wide' => true, 'open' => false])

<div
    id="modal-{{ $id }}"
    data-modal="{{ $id }}"
    @class([
        'fixed inset-0 z-50 items-center justify-center bg-overlay p-4',
        'flex' => $open,
        'hidden' => ! $open,
    ])
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-title-{{ $id }}"
>
    <div @class([
        'flex max-h-[95vh] w-full flex-col overflow-hidden rounded-[20px] bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.1)]',
        'max-w-[1222px]' => $wide,
        'max-w-[842px]' => ! $wide,
    ])>
        {{-- Header --}}
        <div class="flex shrink-0 items-start gap-4 border-b border-border-light p-5">
            <button type="button" data-modal-close class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border-light hover:bg-surface" aria-label="Back">
                <img src="{{ asset('images/inbox/modals/arrow-left.svg') }}" alt="" class="size-5" width="20" height="20">
            </button>
            <div class="min-w-0 flex-1">
                <h2 id="modal-title-{{ $id }}" class="fd-modal-title text-2xl">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="fd-page-note mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            <button type="button" data-modal-close class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface" aria-label="Close">
                <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
            </button>
        </div>

        {{-- Body --}}
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
            {{ $slot }}
        </div>
    </div>
</div>
