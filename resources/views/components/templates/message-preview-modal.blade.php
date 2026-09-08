@props(['closeRoute' => null, 'previewData' => null])

@php
  $closeUrl = $closeRoute ?? route('templates.index');
@endphp

<div
  id="modal-message-preview"
  data-modal="message-preview"
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[rgba(0,0,0,0.6)] px-4 py-8 sm:px-8"
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-message-preview"
>
  <a href="{{ $closeUrl }}" class="absolute inset-0" aria-label="Close preview"></a>

  <div class="relative z-10 my-auto flex w-full max-w-[425px] flex-col gap-4">
    <div class="flex flex-col gap-1">
      <h2 id="modal-title-message-preview" class="fd-preview-title">Message Preview</h2>
      <p class="fd-preview-subtitle">Template preview message look like</p>
    </div>

    <x-templates.phone-frame>
      <x-templates.message-preview-bubble :preview-data="$previewData" />
    </x-templates.phone-frame>
  </div>
</div>
