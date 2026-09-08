@props([
    'action',
    'confirm' => 'Delete this item? This action cannot be undone.',
    'title' => 'Delete item',
    'label' => 'Delete',
    'variant' => 'danger',
    'ajax' => false,
])

<form
  method="POST"
  action="{{ $action }}"
  class="inline"
  data-confirm="{{ $confirm }}"
  data-confirm-title="{{ $title }}"
  data-confirm-label="{{ $label }}"
  data-confirm-variant="{{ $variant }}"
  @if ($ajax) data-drip-delete @endif
  {{ $attributes }}
>
  @csrf
  @method('DELETE')
  <button
    type="submit"
    class="flex size-5 items-center justify-center rounded transition-opacity hover:opacity-70"
    aria-label="{{ $label }}"
    title="{{ $label }}"
  >
    <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-5" width="20" height="20">
  </button>
</form>
