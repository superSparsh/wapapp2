@php
  $draftContacts = old('contacts');

  if (! is_array($draftContacts)) {
      $draftContacts = $contacts->map(fn ($contact) => [
          'id' => $contact->id,
          'type' => $contact->type->value,
          'full_name' => $contact->full_name,
          'contact_info' => $contact->contact_info,
      ])->all();
  }

  if ($draftContacts === []) {
      $draftContacts = [['type' => 'whatsapp', 'full_name' => '', 'contact_info' => '']];
  }
@endphp

<x-profile.layout title="Business Alerts Setup - WapApp" headerTitle="Business Alerts Setup" active="profile.alerts">
  <div class="flex flex-col gap-1">
    <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Manage Notification Contacts</h1>
    <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
      Configure who receives business alert notifications.
    </p>
  </div>

  @if (session('status'))
    <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-600">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-600">
      {{ $errors->first() }}
    </div>
  @endif

  <form action="{{ route('profile.alerts.sync') }}" method="POST" class="mt-6 flex flex-col gap-4" id="alerts-form">
    @csrf

    <div class="w-full max-w-[420px] rounded-lg border-[0.5px] border-border-light bg-elevated p-4 shadow-[0px_0px_1.5px_rgba(0,0,0,0.08)]">
      <label class="flex cursor-pointer items-center gap-2">
        <input type="hidden" name="alerts_enabled" value="0">
        <input type="checkbox" name="alerts_enabled" value="1" class="size-4 rounded border-border" @checked(old('alerts_enabled', $preferences->alerts_enabled))>
        <span class="text-sm font-semibold leading-[1.5] text-text-primary">Enable Notifications System</span>
      </label>
    </div>

    <div class="flex flex-col gap-4 rounded-lg bg-elevated p-4">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-semibold leading-[1.4] text-text-primary">Notification contacts</h2>
        <button type="button" id="add-contact-row" class="inline-flex items-center justify-center rounded border border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2">
          Add New
        </button>
      </div>

      <div id="contact-rows" class="flex flex-col gap-4">
        @foreach ($draftContacts as $index => $contact)
          <div class="grid gap-4 rounded-xl border border-border p-4 lg:grid-cols-[1fr_1fr_1fr_auto]" data-contact-row>
            @if (! empty($contact['id']))
              <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $contact['id'] }}" data-contact-id>
            @endif

            <div class="flex flex-col gap-2">
              <label class="text-sm font-semibold text-text-primary">Type</label>
              <select name="contacts[{{ $index }}][type]" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
                @foreach ($notificationTypes as $value => $label)
                  <option value="{{ $value }}" @selected(($contact['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-semibold text-text-primary">Full Name</label>
              <input type="text" name="contacts[{{ $index }}][full_name]" value="{{ $contact['full_name'] ?? '' }}" placeholder="Enter full name" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
              @error("contacts.{$index}.full_name")
                <p class="text-xs text-[red]">{{ $message }}</p>
              @enderror
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-semibold text-text-primary">Contact</label>
              <input type="text" name="contacts[{{ $index }}][contact_info]" value="{{ $contact['contact_info'] ?? '' }}" placeholder="Email or WhatsApp number" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
              @error("contacts.{$index}.contact_info")
                <p class="text-xs text-[red]">{{ $message }}</p>
              @enderror
            </div>

            <div class="flex items-end justify-end lg:items-center">
              <button
                type="button"
                data-remove-contact
                class="text-xs font-semibold text-[red] underline"
                @if (count($draftContacts) <= 1) hidden @endif
              >
                Remove
              </button>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="flex items-center justify-end">
      <x-ui.button type="submit" class="rounded px-6 py-3 text-xs">Save</x-ui.button>
    </div>
  </form>

  <template id="contact-row-template">
    <div class="grid gap-4 rounded-xl border border-border p-4 lg:grid-cols-[1fr_1fr_1fr_auto]" data-contact-row>
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold text-text-primary">Type</label>
        <select data-name="type" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
          @foreach ($notificationTypes as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold text-text-primary">Full Name</label>
        <input type="text" data-name="full_name" placeholder="Enter full name" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
      </div>
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold text-text-primary">Contact</label>
        <input type="text" data-name="contact_info" placeholder="Email or WhatsApp number" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-primary">
      </div>
      <div class="flex items-end justify-end lg:items-center">
        <button type="button" data-remove-contact class="text-xs font-semibold text-[red] underline">Remove</button>
      </div>
    </div>
  </template>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const rows = document.getElementById('contact-rows');
      const template = document.getElementById('contact-row-template');
      const addButton = document.getElementById('add-contact-row');

      const reindexRows = () => {
        rows.querySelectorAll('[data-contact-row]').forEach((row, index) => {
          const idInput = row.querySelector('[data-contact-id]');
          const typeInput = row.querySelector('[name$="[type]"], [data-name="type"]');
          const nameInput = row.querySelector('[name$="[full_name]"], [data-name="full_name"]');
          const contactInput = row.querySelector('[name$="[contact_info]"], [data-name="contact_info"]');

          if (idInput) {
            idInput.name = `contacts[${index}][id]`;
          }

          if (typeInput) {
            typeInput.name = `contacts[${index}][type]`;
          }

          if (nameInput) {
            nameInput.name = `contacts[${index}][full_name]`;
          }

          if (contactInput) {
            contactInput.name = `contacts[${index}][contact_info]`;
          }
        });

        const rowCount = rows.querySelectorAll('[data-contact-row]').length;
        rows.querySelectorAll('[data-remove-contact]').forEach((button) => {
          button.hidden = rowCount <= 1;
        });
      };

      addButton?.addEventListener('click', () => {
        const clone = template.content.firstElementChild.cloneNode(true);
        rows.appendChild(clone);
        reindexRows();
      });

      rows?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-contact]');
        if (! button) {
          return;
        }

        const row = button.closest('[data-contact-row]');
        if (! row || rows.querySelectorAll('[data-contact-row]').length <= 1) {
          return;
        }

        row.remove();
        reindexRows();
      });

      reindexRows();
    });
  </script>
</x-profile.layout>
