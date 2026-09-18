@php $modal = config('inbox-modals.list-of-choices'); @endphp
<x-inbox.modal id="list-of-choices" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="list">
            <input type="hidden" name="type" value="list">
            <div class="flex flex-col gap-2">
                <x-form.label>Header Text (Optional)</x-form.label>
                <input type="text" name="header" maxlength="60" placeholder="Choose a service" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Body Text <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="3" maxlength="1024" required placeholder="Pick one option from the list" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Footer Text (Optional)</x-form.label>
                <input type="text" name="footer" maxlength="60" placeholder="Tap to choose" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>List Button Text <span class="text-red-500">*</span></x-form.label>
                <input type="text" name="list_button_text" maxlength="20" required value="View options" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Section Title</x-form.label>
                <input type="text" name="sections[0][title]" maxlength="24" placeholder="Options" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-3">
                <x-form.label>Choices (1–3)</x-form.label>
                @foreach ([0, 1, 2] as $index)
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input
                            type="text"
                            name="sections[0][rows][{{ $index }}][title]"
                            maxlength="24"
                            @required($index === 0)
                            placeholder="Option {{ $index + 1 }}"
                            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        >
                        <input
                            type="text"
                            name="sections[0][rows][{{ $index }}][description]"
                            maxlength="72"
                            placeholder="Description (optional)"
                            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        >
                    </div>
                @endforeach
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send List" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
