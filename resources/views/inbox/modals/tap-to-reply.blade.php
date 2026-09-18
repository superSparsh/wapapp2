@php $modal = config('inbox-modals.tap-to-reply'); @endphp
<x-inbox.modal id="tap-to-reply" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="button">
            <input type="hidden" name="type" value="button">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="4" maxlength="1024" required placeholder="How can we help you today?" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Footer Text (Optional)</x-form.label>
                <input type="text" name="footer" maxlength="60" placeholder="Reply below" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-3">
                <x-form.label>Buttons (1–3)</x-form.label>
                @foreach ([0, 1, 2] as $index)
                    <input
                        type="text"
                        name="buttons[{{ $index }}][title]"
                        maxlength="20"
                        @required($index === 0)
                        placeholder="Button {{ $index + 1 }}{{ $index === 0 ? '' : ' (optional)' }}"
                        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                @endforeach
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Buttons" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
