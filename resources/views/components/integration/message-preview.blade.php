@props(['title' => 'Message Preview'])

<x-form-builder.message-preview :title="$title">
  {{ $slot }}
</x-form-builder.message-preview>
