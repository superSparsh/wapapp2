@php
  $menuOpen = $menuOpen ?? false;
  $menuItems = [
    ['modal' => 'send-templates', 'label' => 'Send Templates', 'image' => 'send-templates.png', 'requires_window' => false],
    ['modal' => 'send-whatsapp-flow', 'label' => 'WhatsApp Flow', 'image' => 'ask-questions.png', 'requires_window' => true],
    ['modal' => 'request-payment', 'label' => 'Request Payment', 'image' => 'request-payment.png', 'requires_window' => true],
    ['modal' => 'media', 'label' => 'Media', 'image' => 'media.png', 'requires_window' => true],
    ['modal' => 'location', 'label' => 'Location', 'image' => 'location.png', 'requires_window' => true],
    ['modal' => 'send-contact', 'label' => 'Contact', 'image' => 'contact.png', 'requires_window' => true],
    ['modal' => 'sticker', 'label' => 'Stickers', 'image' => 'stickers.png', 'requires_window' => true],
    ['modal' => 'send-templates-menu', 'label' => 'Buttons & Lists', 'image' => 'buttons-lists.png', 'requires_window' => true],
    ['modal' => 'reactions', 'label' => 'Reactions', 'image' => 'reactions.png', 'requires_window' => true],
  ];
@endphp

<div
  id="inbox-message-menu"
  @class([
    'fixed z-[200] w-[240px] rounded-xl border border-border-light bg-muted-surface p-3 shadow-xl',
    'hidden' => ! $menuOpen,
  ])
  role="menu"
  aria-label="Message actions"
>
  <div class="flex flex-col gap-2">
    @foreach (array_slice($menuItems, 0, 4) as $item)
      @include('inbox.partials.message-menu-item', ['item' => $item])
    @endforeach
  </div>
  <div class="mt-2 flex flex-col gap-2">
    @foreach (array_slice($menuItems, 4) as $item)
      @include('inbox.partials.message-menu-item', ['item' => $item])
    @endforeach
  </div>
</div>
