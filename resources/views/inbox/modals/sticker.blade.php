@php $modal = config('inbox-modals.sticker'); @endphp
<x-inbox.modal id="sticker" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6" data-inbox-sticker-form enctype="multipart/form-data">
            <div class="flex flex-col gap-2">
                <x-form.label for="sticker_file">Sticker File <span class="text-red-500">*</span></x-form.label>
                <input
                    id="sticker_file"
                    name="file"
                    type="file"
                    accept="image/webp,image/png"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm"
                    required
                >
                <p class="text-[10px] text-text-subtle">Supports WebP and PNG formats (max 100KB)</p>
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-sticker-error></p>
            <x-inbox.modal-actions submit="Send Sticker" />
        </form>
    </x-inbox.modal-form>

    <aside class="hidden w-full shrink-0 flex-col gap-4 overflow-y-auto px-2 py-4 lg:flex lg:min-w-0 lg:flex-1">
        <div class="flex flex-col gap-1">
            <h3 class="text-xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">Sticker Preview</h3>
            <p class="text-sm leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">Preview how the sticker will appear</p>
        </div>
        <div class="relative aspect-square w-full max-w-[360px] rounded-xl border border-dashed border-divider bg-elevated" data-inbox-sticker-preview></div>
    </aside>
</x-inbox.modal>
