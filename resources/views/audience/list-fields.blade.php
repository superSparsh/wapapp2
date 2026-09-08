<x-layouts.app title="Manage List Fields - WapApp" active="audience.list-fields">
  @php
    $fieldTypes = [
      ['label' => 'Text', 'value' => 'text'],
      ['label' => 'Number', 'value' => 'number'],
      ['label' => 'Dropdown', 'value' => 'dropdown'],
      ['label' => 'Multiselect', 'value' => 'multiselect'],
      ['label' => 'Checkbox', 'value' => 'checkbox'],
      ['label' => 'Radio', 'value' => 'radio'],
      ['label' => 'Date', 'value' => 'date'],
      ['label' => 'Datetime', 'value' => 'datetime'],
      ['label' => 'Text Area', 'value' => 'textarea'],
    ];
  @endphp

  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <x-audience.list-header :title="$mailList?->name ?? 'Manage List Fields'" :subscribers="(string) ($mailList?->totalContactsCount() ?? 0)" />
      <x-audience.sub-nav active="audience.list-fields" />
    </div>

    @if($mailList)
    <section class="flex flex-col gap-4 p-4 pt-0">
      {{-- Add new field section --}}
      <div class="rounded-lg bg-blue-50 pb-4">
        <div class="flex flex-col gap-2 px-4 pt-3">
          <p class="text-[20px] font-bold leading-[1.5] text-text-primary">New field</p>
          <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Please click on these fields to add</p>
        </div>

        <div class="flex flex-wrap gap-3 px-4 pt-4">
          @foreach ($fieldTypes as $fieldType)
            <form method="POST" action="{{ route('audience.list-fields.store') }}" class="inline">
              @csrf
              <input type="hidden" name="mail_list_id" value="{{ $mailList->uuid }}">
              <input type="hidden" name="label" value="New {{ $fieldType['label'] }}">
              <input type="hidden" name="type" value="{{ $fieldType['value'] }}">
              <button
                type="submit"
                class="flex w-[153px] items-center gap-3 rounded-lg border border-border bg-elevated px-3 py-2.5 text-left hover:border-green-500 hover:bg-green-50 transition-colors"
              >
                <div class="flex items-center justify-center rounded-[6px] border border-green-500 bg-green-50 p-[6px]">
                  <x-icons.list-field-type :type="$fieldType['value']" />
                </div>
                <span class="whitespace-nowrap text-[14px] font-medium text-text-body">
                  {{ $fieldType['label'] }}
                </span>
              </button>
            </form>
          @endforeach
        </div>
      </div>

      {{-- Fields table --}}
      <form method="POST" action="{{ route('audience.list-fields.update') }}" class="flex flex-col gap-4 rounded-[12px] bg-surface p-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="mail_list_id" value="{{ $mailList->uuid }}">

        <div class="text-[20px] font-semibold leading-[1.5] text-text-primary">
          Manage list fields
        </div>

        @if($fields->count() > 0)
        <div class="drop-shadow-[0px_4px_6px_rgba(0,0,0,0.04)] overflow-clip rounded-[12px] bg-transparent">
          {{-- Header --}}
          <div class="flex items-center gap-2 bg-elevated px-2 py-2.5">
            <div class="flex w-[54px] shrink-0 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body whitespace-nowrap">SI. No</p>
            </div>
            <div class="flex min-w-0 flex-1 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Label and Type</p>
            </div>
            <div class="flex w-[120px] shrink-0 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Required?</p>
            </div>
            <div class="flex w-[120px] shrink-0 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Visible</p>
            </div>
            <div class="flex min-w-0 flex-1 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Tag</p>
            </div>
            <div class="flex min-w-0 flex-1 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Default Value</p>
            </div>
            <div class="flex w-[80px] shrink-0 items-center p-2">
              <p class="text-[13px] font-medium leading-[1.5] text-text-body">Actions</p>
            </div>
          </div>

          {{-- Rows --}}
          <div class="flex flex-col">
            @foreach ($fields as $index => $field)
              <div class="flex items-center gap-2 bg-elevated px-2 py-1.5 border-t border-border-sidebar">
                <input type="hidden" name="fields[{{ $index }}][id]" value="{{ $field->uuid }}">
                <div class="flex w-[54px] shrink-0 items-center p-2">
                  <p class="text-[13px] font-normal leading-[1.5] text-text-body whitespace-nowrap">
                    {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                  </p>
                </div>

                <div class="flex min-w-0 flex-1 items-center p-2">
                  <div class="flex flex-1 flex-col gap-2">
                    <div class="text-[13px] font-semibold leading-[1.4] text-text-primary/60">
                      {{ ucfirst($field->type) }}
                    </div>
                    <input
                      type="text"
                      name="fields[{{ $index }}][label]"
                      value="{{ old("fields.{$index}.label", $field->label) }}"
                      placeholder="Enter label"
                      class="w-full rounded-[12px] border border-border bg-elevated px-4 py-3 text-[14px] font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                    <input type="hidden" name="fields[{{ $index }}][type]" value="{{ $field->type }}">
                  </div>
                </div>

                <div class="flex w-[120px] shrink-0 items-center justify-start p-2">
                  <input type="hidden" name="fields[{{ $index }}][required]" value="{{ $field->required ? '1' : '0' }}" data-list-field-input="required-{{ $field->id }}">
                  <x-ui.toggle-switch
                    :active="(bool) $field->required"
                    :disabled="$field->isProtected()"
                    data-list-field-toggle="required-{{ $field->id }}"
                    aria-label="Required"
                  />
                </div>

                <div class="flex w-[120px] shrink-0 items-center justify-start p-2">
                  <input type="hidden" name="fields[{{ $index }}][visible]" value="{{ $field->visible ? '1' : '0' }}" data-list-field-input="visible-{{ $field->id }}">
                  <x-ui.toggle-switch
                    :active="(bool) $field->visible"
                    :disabled="$field->isProtected()"
                    data-list-field-toggle="visible-{{ $field->id }}"
                    aria-label="Visible"
                  />
                </div>

                <div class="flex min-w-0 flex-1 items-center p-2">
                  <div class="flex flex-1 flex-col gap-2">
                    <div class="text-[13px] font-semibold leading-[1.4] text-text-primary/60">SUBSCRIBER</div>
                    <div class="flex items-center gap-1">
                      <span class="text-[13px] text-text-body/60">{SUBSCRIBER_</span>
                      <input
                        type="text"
                        name="fields[{{ $index }}][tag]"
                        value="{{ old("fields.{$index}.tag", $field->tag) }}"
                        placeholder="tag"
                        class="w-full rounded-[8px] border border-border bg-elevated px-3 py-2 text-[13px] font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        {{ $field->isProtected() ? 'readonly' : '' }}
                      >
                      <span class="text-[13px] text-text-body/60">}</span>
                    </div>
                  </div>
                </div>

                <div class="flex min-w-0 flex-1 items-center p-2">
                  <div class="flex flex-1 flex-col gap-2">
                    <div class="text-[13px] font-semibold leading-[1.4] text-text-primary/60">Default</div>
                    <input
                      type="text"
                      name="fields[{{ $index }}][default_value]"
                      value="{{ old("fields.{$index}.default_value", $field->default_value) }}"
                      placeholder="Enter default value"
                      class="w-full rounded-[12px] border border-border bg-elevated px-4 py-3 text-[14px] font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                  </div>
                </div>

                <div class="flex w-[80px] shrink-0 items-center justify-center p-2">
                  @if(! $field->isProtected())
                    <button
                      type="submit"
                      form="delete-list-field-{{ $field->id }}"
                      class="flex items-center justify-center rounded p-1 text-red-500 hover:bg-red-50 transition-colors"
                      aria-label="Delete field"
                    >
                      <x-icons.nav-icon name="trash" class="size-5" />
                    </button>
                  @else
                    <span class="text-[10px] text-text-body/40">Protected</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </div>

        <div class="flex justify-end pt-4">
          <button
            type="submit"
            class="rounded-[4px] bg-green-500 px-4 py-3 text-[14px] font-semibold leading-[1.5] text-primary-2 hover:opacity-90 transition-opacity"
          >
            Save Changes
          </button>
        </div>
      </form>

      @foreach ($fields as $field)
        @if (! $field->isProtected())
          <form
            id="delete-list-field-{{ $field->id }}"
            method="POST"
            action="{{ route('audience.list-fields.destroy', $field) }}"
            class="hidden"
            data-confirm="Delete this field? This cannot be undone."
            data-confirm-title="Delete field"
            data-confirm-label="Delete"
            data-confirm-variant="danger"
          >
            @csrf
            @method('DELETE')
          </form>
        @endif
      @endforeach

      <script>
        document.querySelectorAll('[data-list-field-toggle]').forEach((toggle) => {
          if (toggle.hasAttribute('disabled')) return;

          toggle.addEventListener('click', (e) => {
            e.preventDefault();
            const key = toggle.getAttribute('data-list-field-toggle');
            const input = document.querySelector(`[data-list-field-input="${key}"]`);
            if (!(input instanceof HTMLInputElement)) return;

            const next = input.value !== '1';
            input.value = next ? '1' : '0';
            toggle.setAttribute('aria-checked', next ? 'true' : 'false');
            toggle.classList.toggle('bg-green-500', next);
            toggle.classList.toggle('bg-green-50', !next);
            const knob = toggle.querySelector('span');
            if (knob instanceof HTMLElement) {
              knob.classList.toggle('left-[24px]', next);
              knob.classList.toggle('left-[2px]', !next);
            }
          });
        });
      </script>
      @else
        <div class="rounded-lg border border-border-light bg-elevated p-8 text-center">
          <p class="text-sm text-text-body/70">No custom fields yet. Add one using the buttons above.</p>
        </div>
      @endif
    </section>
    @else
    <section class="p-4 pt-0">
      <div class="rounded-lg border border-border-light bg-elevated p-8 text-center">
        <p class="text-sm text-text-body/70">No list selected. Please select a list from the overview page.</p>
      </div>
    </section>
    @endif
  </div>
</x-layouts.app>
