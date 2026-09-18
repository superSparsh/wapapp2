@php $modal = config('inbox-modals.ask-for-address'); @endphp
<x-inbox.modal id="ask-for-address" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-6" data-inbox-compose-form data-compose-type="address_message">
            <input type="hidden" name="type" value="address_message">
            <div class="flex flex-col gap-2">
                <x-form.label>Message Body <span class="text-red-500">*</span></x-form.label>
                <textarea name="body" rows="3" maxlength="1024" required placeholder="Please share your delivery address" class="w-full resize-none rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"></textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label>Footer Text (Optional)</x-form.label>
                <input type="text" name="footer" maxlength="60" placeholder="Needed for shipping" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-compose-error></p>
            <x-inbox.modal-actions submit="Send Address Request" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
