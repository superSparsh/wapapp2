@props([
    'open' => false,
    'variables' => collect(),
    'builtinVariables' => [],
])

@php
  $initialCustom = collect($variables)->map(fn ($variable) => [
      'name' => $variable->name,
      'label' => $variable->name,
      'display_name' => ucwords(str_replace('_', ' ', $variable->name)),
      'description' => (string) ($variable->data_type?->label() ?? $variable->data_type ?? 'Custom variable'),
      'syntax' => '$('.$variable->name.')',
      'type' => 'custom',
      'category' => 'custom',
      'data_type' => $variable->data_type?->value ?? null,
  ])->values()->all();

  $initialBuiltin = collect($builtinVariables)->map(fn ($variable) => [
      'name' => $variable['name'] ?? '',
      'label' => $variable['display_name'] ?? ($variable['name'] ?? ''),
      'display_name' => $variable['display_name'] ?? ($variable['name'] ?? ''),
      'description' => $variable['description'] ?? '',
      'syntax' => $variable['syntax'] ?? '$('.($variable['name'] ?? '').')',
      'type' => $variable['type'] ?? 'built-in',
      'category' => $variable['category'] ?? 'message',
  ])->values()->all();
@endphp

<div
  id="modal-add-variable"
  data-modal="add-variable"
  data-variables-url="{{ route('templates.api.variables') }}"
  data-create-variable-url="{{ route('templates.variables.create') }}"
  data-initial-variables='@json(['custom' => $initialCustom, 'builtin' => $initialBuiltin])'
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-[rgba(0,0,0,0.6)] p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-add-variable"
>
  <div class="flex w-full max-w-[603px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex w-full items-start justify-end gap-4">
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <h2 id="modal-title-add-variable" class="text-2xl font-bold leading-[1.5] text-text-primary">Select and Add Variable</h2>
        <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Pick a variable to insert as <code>$(name)</code> in your template body.
        </p>
      </div>
      <button type="button" data-variable-close aria-label="Close" class="shrink-0">
        <img src="{{ asset('images/templates/modal-close.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <div class="flex w-full flex-col gap-3 rounded-xl border border-solid border-border-light bg-muted-surface p-4">
      <label class="flex w-full items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
        <img src="{{ asset('images/templates/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
        <input
          type="search"
          id="variable-search"
          placeholder="Search by name or syntax"
          class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none"
        >
      </label>

      <div id="variable-options" class="flex max-h-80 flex-col overflow-y-auto rounded-xl border border-solid border-border bg-elevated">
        <p class="p-4 text-sm text-text-muted" data-variable-loading>Loading variables...</p>
      </div>

      <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Custom variables are created by you. Built-in variables come from message and subscriber context.
      </p>
    </div>

    <div class="flex w-full items-center justify-end gap-3">
      <a href="{{ route('templates.variables.create') }}" class="fd-btn inline-flex items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-green-500 transition-colors hover:bg-green-50">
        Create Variable
      </a>
      <button type="button" data-variable-close class="fd-btn inline-flex w-[120px] items-center justify-center rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90">
        Close
      </button>
    </div>
  </div>
</div>
