@props([
    'label' => 'Delete selected',
])

<div data-bulk-actions {{ $attributes->class(['hidden items-center gap-3']) }}>
  <span class="text-sm text-text-muted"><span data-bulk-count>0</span> selected</span>
  <button
    type="button"
    data-bulk-delete
    class="fd-btn-sm rounded border border-red-500 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
  >
    {{ $label }}
  </button>
</div>
