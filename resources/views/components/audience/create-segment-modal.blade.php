@props([
    'mailListId' => null,
    'listFields' => [],
])

@php
  $coreFields = [
      ['tag' => 'phone', 'label' => 'Phone'],
      ['tag' => 'phone_number', 'label' => 'WhatsApp Number'],
      ['tag' => 'FIRST_NAME', 'label' => 'First Name'],
      ['tag' => 'LAST_NAME', 'label' => 'Last Name'],
      ['tag' => 'name', 'label' => 'Full Name'],
      ['tag' => 'email', 'label' => 'Email'],
      ['tag' => 'country_code', 'label' => 'Country code'],
      ['tag' => 'status', 'label' => 'Status'],
      ['tag' => 'created_at', 'label' => 'Created at'],
  ];

  $listFieldOptions = collect($listFields)->map(fn ($f) => [
      'tag' => (string) ($f->tag ?? $f['tag'] ?? ''),
      'label' => (string) ($f->label ?? $f['label'] ?? ''),
  ])->filter(fn ($f) => $f['tag'] !== '')->values();

  // Always expose core contact fields (incl. First/Last Name); merge custom list fields after.
  $fieldOptions = collect($coreFields)
      ->concat($listFieldOptions)
      ->unique(fn ($f) => strtoupper($f['tag']))
      ->values();

  // Prefer First Name / Last Name labels when list fields use those tags.
  $fieldOptions = $fieldOptions->map(function (array $field) use ($listFieldOptions): array {
      $tag = strtoupper($field['tag']);
      if ($tag === 'FIRST_NAME') {
          $field['tag'] = 'FIRST_NAME';
          $field['label'] = $listFieldOptions->firstWhere(fn ($f) => strtoupper($f['tag']) === 'FIRST_NAME')['label'] ?? 'First Name';
      }
      if ($tag === 'LAST_NAME') {
          $field['tag'] = 'LAST_NAME';
          $field['label'] = $listFieldOptions->firstWhere(fn ($f) => strtoupper($f['tag']) === 'LAST_NAME')['label'] ?? 'Last Name';
      }

      return $field;
  });

  $operators = [
      'equals' => 'Equal',
      'not_equals' => 'Not equal',
      'contains' => 'Contains',
      'starts_with' => 'Starts with',
      'ends_with' => 'Ends with',
      'greater_than' => 'Greater than',
      'less_than' => 'Less than',
      'is_empty' => 'Is empty',
      'is_not_empty' => 'Is not empty',
  ];
@endphp

<div id="modal-create-segment" data-modal="create-segment" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-create-segment">
  <div class="flex max-h-[90vh] w-full max-w-[660px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-create-segment" class="text-2xl font-bold leading-[1.5] text-text-primary" data-segment-modal-title>Create segment</h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50" data-segment-modal-subtitle>Filter subscribers with one or more conditions</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form
      method="POST"
      action="{{ route('audience.segments.store') }}"
      class="space-y-4"
      data-segment-form
      data-segment-store-url="{{ route('audience.segments.store') }}"
    >
      @csrf
      <input type="hidden" name="_method" value="POST" data-segment-method>
      @if ($mailListId)
        <input type="hidden" name="mail_list_id" value="{{ $mailListId }}">
      @endif

      <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-4 md:flex-row">
            <div class="min-w-0 flex-1">
              <label for="segment_name" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                Segment Name <span class="text-red-500">*</span>
              </label>
              <input
                id="segment_name"
                name="name"
                type="text"
                required
                placeholder="Enter Segment Name"
                data-segment-name
                class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
            </div>

            <div class="min-w-0 flex-1">
              <label for="segment_match_type" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                How to combine the conditions <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <select
                  id="segment_match_type"
                  name="match_type"
                  data-segment-match
                  class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                  <option value="all" selected>All (match every condition)</option>
                  <option value="any">Any (match at least one)</option>
                </select>
                <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <p class="text-base font-bold leading-[1.2] text-text-primary">Conditions</p>
            <div class="space-y-4" data-segment-conditions>
              <div class="flex flex-col gap-3 lg:flex-row lg:items-end" data-condition-row>
                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">
                    Field <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select
                      name="conditions[0][field]"
                      required
                      class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                      <option value="">Select field</option>
                      @foreach ($fieldOptions as $field)
                        <option value="{{ $field['tag'] }}">{{ $field['label'] }}</option>
                      @endforeach
                    </select>
                    <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                  </div>
                </div>

                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">
                    Operator <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <select
                      name="conditions[0][type]"
                      required
                      class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    >
                      @foreach ($operators as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                      @endforeach
                    </select>
                    <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                  </div>
                </div>

                <div class="min-w-0 flex-1">
                  <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">Value</label>
                  <input
                    type="text"
                    name="conditions[0][value]"
                    placeholder="Value"
                    class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                </div>

                <button type="button" data-remove-condition class="flex h-[48px] w-[48px] items-center justify-center self-stretch lg:self-auto" aria-label="Remove condition">
                  <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </div>
            </div>
          </div>

          <div>
            <button type="button" data-add-condition class="fd-btn rounded border border-green-500 px-6 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">
              Add Condition
            </button>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90" data-segment-submit>
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<template id="segment-condition-template">
  <div class="flex flex-col gap-3 lg:flex-row lg:items-end" data-condition-row>
    <div class="min-w-0 flex-1">
      <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">Field <span class="text-red-500">*</span></label>
      <div class="relative">
        <select data-name-field="field" required class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
          <option value="">Select field</option>
          @foreach ($fieldOptions as $field)
            <option value="{{ $field['tag'] }}">{{ $field['label'] }}</option>
          @endforeach
        </select>
        <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
      </div>
    </div>
    <div class="min-w-0 flex-1">
      <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">Operator <span class="text-red-500">*</span></label>
      <div class="relative">
        <select data-name-field="type" required class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
          @foreach ($operators as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
        <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
      </div>
    </div>
    <div class="min-w-0 flex-1">
      <label class="mb-1 block text-sm font-semibold leading-[1.4] text-text-primary">Value</label>
      <input type="text" data-name-field="value" placeholder="Value" class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
    </div>
    <button type="button" data-remove-condition class="flex h-[48px] w-[48px] items-center justify-center self-stretch lg:self-auto" aria-label="Remove condition">
      <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
    </button>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('[data-segment-form]');
  const container = document.querySelector('[data-segment-conditions]');
  const template = document.getElementById('segment-condition-template');
  const addBtn = document.querySelector('[data-add-condition]');
  const methodInput = form?.querySelector('[data-segment-method]');
  const titleEl = document.querySelector('[data-segment-modal-title]');
  const subtitleEl = document.querySelector('[data-segment-modal-subtitle]');
  const nameInput = form?.querySelector('[data-segment-name]');
  const matchSelect = form?.querySelector('[data-segment-match]');
  const submitBtn = form?.querySelector('[data-segment-submit]');
  const storeUrl = form?.dataset.segmentStoreUrl || '';

  if (!form || !container || !template || !addBtn) return;

  const reindex = () => {
    container.querySelectorAll('[data-condition-row]').forEach((row, index) => {
      row.querySelectorAll('[name^="conditions["], [data-name-field]').forEach((el) => {
        const key = el.getAttribute('data-name-field') || (el.getAttribute('name') || '').replace(/^conditions\[\d+]\[(.+)]$/, '$1');
        if (!key) return;
        el.setAttribute('name', `conditions[${index}][${key}]`);
      });
    });
  };

  const clearConditions = () => {
    container.querySelectorAll('[data-condition-row]').forEach((row) => row.remove());
  };

  const appendCondition = (condition = {}) => {
    const node = template.content.cloneNode(true);
    const row = node.querySelector('[data-condition-row]');
    if (!row) return;

    const fieldSelect = row.querySelector('[data-name-field="field"]');
    const typeSelect = row.querySelector('[data-name-field="type"]');
    const valueInput = row.querySelector('[data-name-field="value"]');

    if (fieldSelect instanceof HTMLSelectElement && condition.field) {
      fieldSelect.value = condition.field;
    }
    if (typeSelect instanceof HTMLSelectElement && condition.type) {
      typeSelect.value = condition.type;
    }
    if (valueInput instanceof HTMLInputElement) {
      valueInput.value = condition.value != null ? String(condition.value) : '';
    }

    container.appendChild(node);
  };

  const resetCreateMode = () => {
    form.action = storeUrl;
    if (methodInput) methodInput.value = 'POST';
    if (titleEl) titleEl.textContent = 'Create segment';
    if (subtitleEl) subtitleEl.textContent = 'Filter subscribers with one or more conditions';
    if (submitBtn) submitBtn.textContent = 'Save';
    if (nameInput) nameInput.value = '';
    if (matchSelect) matchSelect.value = 'all';
    clearConditions();
    appendCondition();
    reindex();
  };

  const openEditMode = (trigger) => {
    const updateUrl = trigger.dataset.segmentUpdateUrl || '';
    const name = trigger.dataset.segmentName || '';
    const match = trigger.dataset.segmentMatch || 'all';
    let conditions = [];
    try {
      conditions = JSON.parse(trigger.dataset.segmentConditions || '[]');
    } catch {
      conditions = [];
    }
    if (!Array.isArray(conditions) || conditions.length === 0) {
      conditions = [{}];
    }

    form.action = updateUrl;
    if (methodInput) methodInput.value = 'PUT';
    if (titleEl) titleEl.textContent = 'Edit segment';
    if (subtitleEl) subtitleEl.textContent = 'Update segment name and conditions';
    if (submitBtn) submitBtn.textContent = 'Update';
    if (nameInput) nameInput.value = name;
    if (matchSelect) matchSelect.value = match === 'any' ? 'any' : 'all';

    clearConditions();
    conditions.forEach((condition) => appendCondition(condition || {}));
    reindex();
  };

  addBtn.addEventListener('click', () => {
    appendCondition();
    reindex();
  });

  container.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-remove-condition]');
    if (!btn) return;
    const rows = container.querySelectorAll('[data-condition-row]');
    if (rows.length <= 1) return;
    btn.closest('[data-condition-row]')?.remove();
    reindex();
  });

  document.querySelectorAll('[data-open-modal="create-segment"]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      if (trigger.hasAttribute('data-segment-edit')) {
        openEditMode(trigger);
      } else {
        resetCreateMode();
      }
    });
  });
});
</script>
