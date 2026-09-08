@php $modal = config('inbox-modals.send-templates'); @endphp
<x-inbox.modal id="send-templates" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6" data-inbox-template-form>
            <div class="flex flex-col gap-2">
                <x-form.label for="template_code">Select Template <span class="text-red-500">*</span></x-form.label>
                <select
                    id="template_code"
                    name="template_code"
                    data-inbox-template-select
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    required
                >
                    <option value="">Loading templates...</option>
                </select>
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-template-error></p>
            <x-inbox.modal-actions submit="Send Template" />
        </form>
    </x-inbox.modal-form>
    <x-inbox.phone-preview />
</x-inbox.modal>
