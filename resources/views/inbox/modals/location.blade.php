@php $modal = config('inbox-modals.location'); @endphp
<x-inbox.modal id="location" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-8" data-inbox-location-form>
            <x-form.input id="location_latitude" name="latitude" type="number" step="any" placeholder="28.6139" required>
                <x-slot:label>Latitude <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <x-form.input id="location_longitude" name="longitude" type="number" step="any" placeholder="77.2090" required>
                <x-slot:label>Longitude <span class="text-red-500">*</span></x-slot:label>
            </x-form.input>
            <p class="hidden text-xs text-red-500" data-inbox-location-error></p>
            <x-inbox.modal-actions submit="Send Location" />
        </form>
    </x-inbox.modal-form>

    <aside class="hidden w-full shrink-0 flex-col gap-4 overflow-y-auto px-2 py-4 lg:flex lg:w-[620px]">
        <div class="flex flex-col gap-1">
            <h3 class="text-xl font-bold leading-[1.5] text-text-primary" style="font-family: var(--font-display)">Select on map</h3>
            <p class="text-sm leading-[1.4] text-text-subtle opacity-50" style="font-family: var(--font-display)">Template preview message look like</p>
        </div>
        <div class="relative h-[437px] w-full max-w-[604px] overflow-hidden rounded-lg">
            <img
                src="{{ asset('images/inbox/modals/location-map.png') }}"
                alt="Select location on map"
                class="size-full object-cover"
                width="604"
                height="437"
            >
        </div>
    </aside>
</x-inbox.modal>
