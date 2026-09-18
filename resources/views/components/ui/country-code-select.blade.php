@props([
    'id' => 'country_code',
    'name' => 'country_code',
    'selected' => null,
    'required' => false,
])

@php
    $countries = \App\Support\PhoneCountryCatalog::all();
    $selectedDial = \App\Support\PhoneCountryCatalog::selectedDialCode($selected);
@endphp

<div class="relative">
  <select
    id="{{ $id }}"
    name="{{ $name }}"
    @required($required)
    {{ $attributes->class([
      'w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
    ]) }}
  >
    @foreach ($countries as $country)
      <option value="{{ $country['d_code'] }}" @selected($country['d_code'] === $selectedDial)>
        {{ $country['name'] }} ({{ $country['d_code'] }})
      </option>
    @endforeach
  </select>
  <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
</div>
