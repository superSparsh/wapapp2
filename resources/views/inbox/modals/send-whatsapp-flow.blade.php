@php $modal = config('inbox-modals.send-whatsapp-flow'); @endphp
<x-inbox.modal id="send-whatsapp-flow" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6" data-inbox-flow-form>
            <div class="flex flex-col gap-2">
                <x-form.label for="inbox_flow_id">Published Flow <span class="text-red-500">*</span></x-form.label>
                <select
                    id="inbox_flow_id"
                    name="flow_id"
                    data-inbox-flow-select
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    required
                >
                    <option value="">Loading flows...</option>
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label for="inbox_flow_body">Message Body <span class="text-red-500">*</span></x-form.label>
                <textarea
                    id="inbox_flow_body"
                    name="body"
                    rows="3"
                    maxlength="1024"
                    required
                    placeholder="Please fill out this form"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >Please fill out this form</textarea>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label for="inbox_flow_cta">Button CTA <span class="text-red-500">*</span></x-form.label>
                <input
                    id="inbox_flow_cta"
                    name="flow_cta"
                    type="text"
                    maxlength="30"
                    required
                    value="Open"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-flow-error></p>
            <x-inbox.modal-actions submit="Send Flow" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
