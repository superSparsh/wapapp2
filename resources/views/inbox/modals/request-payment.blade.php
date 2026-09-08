@php $modal = config('inbox-modals.request-payment'); @endphp
<x-inbox.modal id="request-payment" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
$form <form class="flex flex-col gap-6" data-inbox-payment-form data-no-loader>
            <x-form.input id="payment_amount" name="amount" type="number" min="1" step="0.01" placeholder="1000.00" required>
                <x-slot:label>Amount <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <x-form.input id="payment_description" name="description" placeholder="Order #1234" required>
                <x-slot:label>Description <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <p class="text-xs text-text-subtle">Creates a Razorpay payment link and sends it on WhatsApp using your Commerce payment template.</p>
            <p class="hidden text-xs text-red-500" data-inbox-payment-error></p>
            <x-inbox.modal-actions submit="Send Payment Link" />
        </form>
    </x-inbox.modal-form>
    <x-inbox.phone-preview />
</x-inbox.modal>
