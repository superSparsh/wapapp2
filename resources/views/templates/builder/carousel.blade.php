@php
  $cards = old('cards', $payload['carousel']['cards'] ?? []);
  if (empty($cards)) {
      $cards = [
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'buttons' => [['text' => '', 'type' => 'QUICK_REPLY', 'url' => '']]],
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'buttons' => [['text' => '', 'type' => 'QUICK_REPLY', 'url' => '']]],
      ];
  }
  $minCards = $minCards ?? (int) config('templates.carousel_min_cards', 2);
  $maxCards = $maxCards ?? (int) config('templates.carousel_max_cards', 10);
  $introBody = old('carousel_body', $payload['carousel']['body'] ?? ($payload['body']['text'] ?? ''));
@endphp

<x-templates.builder-layout active="carousel" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null" :can-use-carousel="true">
  <form
    method="post"
    action="{{ route('templates.builder.carousel.save', $template) }}"
    class="flex flex-col gap-4"
    id="carousel-builder-form"
    data-validate-form
    data-carousel-min="{{ $minCards }}"
    data-carousel-max="{{ $maxCards }}"
  >
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-4">
      <div>
        <p class="text-sm font-semibold text-text-body">Carousel intro (Body)</p>
        <p class="mt-1 text-xs text-text-subtle">Shown above the cards. Uses the Body step text by default — edit here if needed.</p>
        <textarea
          name="carousel_body"
          rows="3"
          maxlength="{{ (int) config('templates.body_limit', 1024) }}"
          class="fd-input mt-2 w-full rounded-xl border border-border p-3"
          placeholder="Hi {{name}}, check these offers"
        >{{ $introBody }}</textarea>
        <x-ui.field-error field="carousel_body" />
      </div>

      <p class="text-sm text-text-subtle">Add {{ $minCards }}–{{ $maxCards }} cards. Each card needs media (image/video URL), body (max 160 chars), and up to 2 buttons.</p>
      <div id="carousel-cards-root" data-saved-cards='@json($cards)' class="flex flex-col gap-4"></div>
      <button type="button" id="add-carousel-card" class="fd-btn-sm mt-2 w-fit rounded border border-green-500 px-4 py-2 text-green-500">+ Add card</button>
      <x-ui.field-error field="cards" />
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>
</x-templates.builder-layout>
