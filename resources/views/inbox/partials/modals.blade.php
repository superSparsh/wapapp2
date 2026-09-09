@php
  $activeModal = $activeModal ?? null;
  $modalOpen = fn (string $id): bool => $activeModal === $id;
@endphp

@include('inbox.modals.send-templates-menu', ['open' => $modalOpen('send-templates-menu')])
@include('inbox.modals.send-templates', ['open' => $modalOpen('send-templates')])
@include('inbox.modals.send-whatsapp-flow', ['open' => $modalOpen('send-whatsapp-flow')])
@include('inbox.modals.media', ['open' => $modalOpen('media')])
@include('inbox.modals.location', ['open' => $modalOpen('location')])
@include('inbox.modals.sticker', ['open' => $modalOpen('sticker')])
@include('inbox.modals.tap-to-reply', ['open' => $modalOpen('tap-to-reply')])
@include('inbox.modals.list-of-choices', ['open' => $modalOpen('list-of-choices')])
@include('inbox.modals.share-many-products', ['open' => $modalOpen('share-many-products')])
@include('inbox.modals.share-one-product', ['open' => $modalOpen('share-one-product')])
@include('inbox.modals.share-one-product-alt', ['open' => $modalOpen('share-one-product-alt')])
@include('inbox.modals.website-button', ['open' => $modalOpen('website-button')])
@include('inbox.modals.ask-few-questions', ['open' => $modalOpen('ask-few-questions')])
@include('inbox.modals.ask-for-location', ['open' => $modalOpen('ask-for-location')])
@include('inbox.modals.ask-for-address', ['open' => $modalOpen('ask-for-address')])
@include('inbox.modals.send-contact', ['open' => $modalOpen('send-contact')])
@include('inbox.modals.add-contact', ['open' => $modalOpen('add-contact')])
@include('inbox.modals.request-payment', ['open' => $modalOpen('request-payment')])
@include('inbox.modals.reactions', ['open' => $modalOpen('reactions')])
