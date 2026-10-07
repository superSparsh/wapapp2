@props([
    'fields' => [],
    'values' => [],
    'prefix' => 'custom_fields',
])

@php
  $fields = collect($fields);
  $values = is_array($values) ? $values : [];
@endphp

@if ($fields->isNotEmpty())
  <div class="flex flex-col gap-4 border-t border-border-light pt-4">
    <p class="text-sm font-semibold text-text-primary">Custom fields</p>
    <div class="grid gap-4 sm:grid-cols-2">
      @foreach ($fields as $field)
        @php
          $tag = (string) $field->tag;
          $inputId = 'list_field_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $tag);
          $name = $prefix.'['.$tag.']';
          $oldKey = $prefix.'.'.$tag;
          $rawValue = old($oldKey, $values[$tag] ?? $field->default_value);
          $required = (bool) $field->required;
          $type = (string) $field->type;
          $options = $field->relationLoaded('options') ? $field->options : collect();
        @endphp
        <div @class(['min-w-0', 'sm:col-span-2' => in_array($type, ['textarea', 'multiselect', 'checkbox'], true)])>
          <label for="{{ $inputId }}" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
            {{ $field->label }}
            @if ($required)
              <span class="text-red-500">*</span>
            @endif
          </label>

          @if ($type === 'textarea')
            <textarea
              id="{{ $inputId }}"
              name="{{ $name }}"
              rows="3"
              @required($required)
              class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >{{ is_array($rawValue) ? implode(', ', $rawValue) : $rawValue }}</textarea>
          @elseif ($type === 'dropdown' || $type === 'radio')
            <select
              id="{{ $inputId }}"
              name="{{ $name }}"
              @required($required)
              data-select-variant="default"
              class="w-full min-w-0"
            >
              <option value="">Select {{ $field->label }}</option>
              @foreach ($options as $option)
                <option value="{{ $option->value }}" @selected((string) $rawValue === (string) $option->value)>
                  {{ $option->label }}
                </option>
              @endforeach
            </select>
          @elseif ($type === 'multiselect' || $type === 'checkbox')
            @php
              $selected = is_array($rawValue)
                ? array_map('strval', $rawValue)
                : array_values(array_filter(array_map('trim', explode(',', (string) $rawValue))));
            @endphp
            <div class="flex flex-col gap-2 rounded-[12px] border border-border bg-elevated p-3">
              @forelse ($options as $option)
                <label class="flex items-center gap-2 text-sm text-text-body">
                  <input
                    type="checkbox"
                    name="{{ $name }}[]"
                    value="{{ $option->value }}"
                    class="size-4 rounded border-border"
                    @checked(in_array((string) $option->value, $selected, true))
                  >
                  {{ $option->label }}
                </label>
              @empty
                <p class="text-xs text-text-subtle">No options configured for this field.</p>
              @endforelse
            </div>
          @elseif ($type === 'number')
            <input
              id="{{ $inputId }}"
              name="{{ $name }}"
              type="number"
              value="{{ is_array($rawValue) ? '' : $rawValue }}"
              @required($required)
              class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          @elseif ($type === 'date')
            <input
              id="{{ $inputId }}"
              name="{{ $name }}"
              type="date"
              value="{{ is_array($rawValue) ? '' : $rawValue }}"
              @required($required)
              class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          @elseif ($type === 'datetime')
            <input
              id="{{ $inputId }}"
              name="{{ $name }}"
              type="datetime-local"
              value="{{ is_array($rawValue) ? '' : $rawValue }}"
              @required($required)
              class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          @else
            <input
              id="{{ $inputId }}"
              name="{{ $name }}"
              type="text"
              value="{{ is_array($rawValue) ? implode(', ', $rawValue) : $rawValue }}"
              @required($required)
              placeholder="Enter {{ $field->label }}"
              class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          @endif
          @error($oldKey)
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
          @enderror
        </div>
      @endforeach
    </div>
  </div>
@endif
