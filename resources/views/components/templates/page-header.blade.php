@props(['title'])

<div class="flex flex-col gap-1">
  <h1 class="fd-page-title">{{ $title }}</h1>
  <p class="fd-page-note max-w-[854px]">
    Note: The parent WhatsApp template, which is the first template of each chatbot in the list, should be unique to ensure the correct flow. Once the flow has been saved, the first template cannot be edited or changed. A new flow must be created instead.
  </p>
</div>
