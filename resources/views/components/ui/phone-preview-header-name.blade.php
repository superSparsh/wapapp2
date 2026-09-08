@props([
    'size' => 'default',
    'name' => null,
])

@php
    $displayName = $name ?? \App\Support\PreviewBusinessName::resolve();
@endphp

<span
  {{ $attributes->merge([
      'class' => 'phone-preview-header-name phone-preview-header-name--' . $size,
      'title' => $displayName,
  ]) }}
  aria-hidden="true"
  style="margin-top: -10px; margin-left: 6px; background: #377e6b; font-size: 14px; width: 161px;"
>{{ $displayName }}</span>
