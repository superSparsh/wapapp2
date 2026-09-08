@php
  $cards = old('cards', $payload['carousel']['cards'] ?? []);
  if (empty($cards)) {
      $cards = [
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'buttons' => []],
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'buttons' => []],
      ];
  }
  $minCards = $minCards ?? (int) config('templates.carousel_min_cards', 2);
  $maxCards = $maxCards ?? (int) config('templates.carousel_max_cards', 10);
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

    <div class="rounded-lg bg-elevated p-4">
      <p class="mb-4 text-sm text-text-subtle">Add {{ $minCards }}–{{ $maxCards }} cards. Each card needs a body (max 160 chars) and header media URL.</p>
      <div id="carousel-cards-root" data-saved-cards='@json($cards)' class="flex flex-col gap-4"></div>
      <button type="button" id="add-carousel-card" class="fd-btn-sm mt-4 rounded border border-green-500 px-4 py-2 text-green-500">+ Add card</button>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="fd-btn-sm rounded bg-green-500 px-4 py-3 text-primary-2">Next</button>
    </div>
  </form>
</x-templates.builder-layout>
