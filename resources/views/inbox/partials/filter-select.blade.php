@props([
  'name',
  'action',
  'options' => [],
  'selected' => null,
  'hidden' => [],
  'minWidth' => 'min-w-[148px]',
  'formClass' => null,
])

<form method="get" action="{{ $action }}" @class([$minWidth, 'shrink-0' => ! $formClass, $formClass => filled($formClass)])>
  @foreach ($hidden as $key => $value)
    @if ($value !== null && $value !== '')
      <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
  @endforeach

  <select
    name="{{ $name }}"
    data-select-variant="filter"
    class="fd-filter-label w-full cursor-pointer rounded-lg bg-elevated py-3 pl-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
    onchange="this.form.submit()"
  >
    @foreach ($options as $option)
      @php
        $value = is_array($option) ? ($option['value'] ?? '') : $option;
        $label = is_array($option) ? ($option['label'] ?? $value) : $option;
      @endphp
      <option value="{{ $value }}" @selected((string) $selected === (string) $value)>
        {{ $label }}
      </option>
    @endforeach
  </select>
</form>
