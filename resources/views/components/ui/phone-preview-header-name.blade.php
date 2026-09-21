@props([
    'size' => 'default',
    'name' => null,
])

@php
    $displayName = $name ?? \App\Support\PreviewBusinessName::resolve();
    $sizeClass = $size === 'compact'
        ? 'phone-preview-header-name--compact'
        : 'phone-preview-header-name--default';
@endphp

<span
  {{ $attributes->merge([
      'class' => "phone-preview-header-name {$sizeClass}",
      'title' => $displayName,
  ]) }}
  aria-hidden="true"
>{{ $displayName }}</span>
