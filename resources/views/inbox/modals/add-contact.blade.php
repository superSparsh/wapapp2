@php $modal = config('inbox-modals.add-contact'); @endphp
<x-inbox.modal id="add-contact" :title="$modal['title']" :subtitle="$modal['subtitle']" :wide="false" :open="$open ?? false">
  <x-inbox.modal-form>
    <form class="flex flex-col gap-6" data-inbox-add-contact-form>
      <x-form.input id="add_contact_name" name="name" placeholder="Enter contact name" required>
        <x-slot:label>Name <span class="text-red-500">*</span></x-slot:label>
      </x-form.input>

      <div class="flex flex-col gap-4 sm:flex-row">
        <div class="w-full sm:w-[160px]">
          <label for="add_contact_country_code" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
            Country Code <span class="text-red-500">*</span>
          </label>
          <x-ui.select id="add_contact_country_code" name="country_code" required>
            <option value="91" selected>India (+91)</option>
            <option value="1">USA (+1)</option>
            <option value="44">UK (+44)</option>
            <option value="971">UAE (+971)</option>
          </x-ui.select>
        </div>

        <div class="min-w-0 flex-1">
          <x-form.input id="add_contact_phone" name="phone" type="tel" inputmode="numeric" placeholder="Enter phone number" required>
            <x-slot:label>Phone Number <span class="text-red-500">*</span></x-slot:label>
          </x-form.input>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="add_contact_response_type" class="text-sm font-semibold leading-[1.4] text-text-primary">
          Response Type <span class="text-red-500">*</span>
        </label>
        <x-ui.select id="add_contact_response_type" name="response_type" required>
          <option value="human_response" selected>Human Response</option>
          <option value="ai_response">AI Response</option>
        </x-ui.select>
      </div>

      <p class="hidden text-xs text-red-500" data-inbox-add-contact-error></p>
      <x-inbox.modal-actions submit="Save Contact" />
    </form>
  </x-inbox.modal-form>
</x-inbox.modal>
