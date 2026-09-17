@php $modal = config('inbox-modals.send-contact'); @endphp

<x-inbox.modal id="send-contact" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
    <x-inbox.modal-form :divided="false">
        <form class="flex flex-col gap-4" data-inbox-contact-form>
            <x-form.input id="contact_name" name="name" placeholder="Enter name" required>
                <x-slot:label>Name <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input id="contact_first_name" name="first_name" placeholder="Enter first name">
                    <x-slot:label>First Name</x-slot:label>
                </x-form.input>
                <x-form.input id="contact_last_name" name="last_name" placeholder="Enter last name">
                    <x-slot:label>Last Name</x-slot:label>
                </x-form.input>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input id="contact_phone" name="phone" type="tel" placeholder="919876543210" required>
                    <x-slot:label>Phone <span class="text-red-500">*</span></x-slot:label>
                </x-form.input>
                <div class="flex flex-col gap-2">
                    <x-form.label for="contact_phone_type">Phone Type</x-form.label>
                    <select
                        id="contact_phone_type"
                        name="phone_type"
                        class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                        <option value="CELL" selected>CELL</option>
                        <option value="WORK">WORK</option>
                        <option value="HOME">HOME</option>
                    </select>
                </div>
            </div>

            <x-form.input id="contact_email" name="email" type="email" placeholder="name@example.com">
                <x-slot:label>Email</x-slot:label>
            </x-form.input>

            <x-form.input id="contact_company" name="company" placeholder="Company name">
                <x-slot:label>Company</x-slot:label>
            </x-form.input>

            <p class="hidden text-xs text-red-500" data-inbox-contact-error></p>
            <x-inbox.modal-actions submit="Send Contact" />
        </form>
    </x-inbox.modal-form>
</x-inbox.modal>
