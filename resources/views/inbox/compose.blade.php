<x-layouts.app title="Inbox Compose - WapApp" active="inbox">
  <div class="flex flex-col">
    <div class="flex flex-col gap-4 p-4">
      <x-ui.page-header
        title="Inbox"
        subtitle="Note: The parent WhatsApp template, which is the first template of each chatbot in the list, should be unique to ensure the correct flow. Once the flow has been saved, the first template cannot be edited or changed. A new flow must be created instead."
      />

      @include('inbox.partials.toolbar')
    </div>

    <div class="flex flex-col gap-4 bg-surface p-4 lg:flex-row">
      @include('inbox.partials.contact-list', ['composeHref' => route('inbox.compose')])

      @include('inbox.partials.chat-panel', ['menuOpen' => true])
    </div>
  </div>

  @include('inbox.partials.modals', ['activeModal' => $activeModal ?? null])
</x-layouts.app>
