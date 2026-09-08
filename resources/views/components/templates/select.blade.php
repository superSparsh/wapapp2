@props([
    'options' => [],
    'name' => 'template_code',
    'id' => 'template_code',
    'selected' => '',
    'required' => false,
    'class' => 'w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
])

<select
  id="{{ $id }}"
  name="{{ $name }}"
  @if ($required) required @endif
  {{ $attributes->merge(['class' => $class]) }}
>
  <option value="">Select template</option>
  @foreach ($options as $option)
    <option value="{{ $option['code'] }}" @selected($selected === $option['code'])>
      {{ $option['name'] }} @if (! empty($option['category'])) ({{ $option['category'] }}) @endif
    </option>
  @endforeach
</select>
