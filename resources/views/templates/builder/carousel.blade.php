@php
  use App\Domains\Templates\Services\TemplateMediaService;

  $mediaService = app(TemplateMediaService::class);
  $cards = old('cards', $payload['carousel']['cards'] ?? []);
  if (empty($cards)) {
      $cards = [
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'media_path' => '', 'use_url' => false, 'buttons' => [['text' => '', 'type' => 'QUICK_REPLY', 'url' => '']]],
          ['header' => 'IMAGE', 'body' => '', 'media_url' => '', 'media_path' => '', 'use_url' => false, 'buttons' => [['text' => '', 'type' => 'QUICK_REPLY', 'url' => '']]],
      ];
  }

  $cards = collect($cards)->map(function (array $card) use ($mediaService): array {
      $path = trim((string) ($card['media_path'] ?? ''));
      $url = trim((string) ($card['media_url'] ?? ''));
      $preview = $path !== '' ? $mediaService->previewUrl($path) : ($url !== '' ? $url : null);

      return array_merge($card, [
          'media_path' => $path,
          'media_url' => $url,
          'media_preview_url' => $preview,
          'use_url' => (bool) ($card['use_url'] ?? false),
      ]);
  })->values()->all();

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
    enctype="multipart/form-data"
    data-validate-form
    data-carousel-min="{{ $minCards }}"
    data-carousel-max="{{ $maxCards }}"
    data-carousel-upload-url="{{ route('templates.builder.carousel.media', $template) }}"
  >
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-sm font-semibold text-text-body">Carousel intro (Body)</p>
          <p class="mt-1 text-xs text-text-subtle">Shown above the cards. Uses the Body step text by default — edit here if needed.</p>
        </div>
        <button
          type="button"
          data-open-modal="carousel-guidelines"
          class="fd-btn-sm inline-flex items-center gap-1.5 rounded border border-green-500 px-3 py-1.5 text-xs font-semibold text-green-600 hover:bg-green-50"
          title="Carousel Template Guidelines"
        >
          <span aria-hidden="true">📘</span>
          Guidelines
        </button>
      </div>

      <div>
        <textarea
          name="carousel_body"
          rows="3"
          maxlength="{{ (int) config('templates.body_limit', 1024) }}"
          class="fd-input mt-2 w-full rounded-xl border border-border p-3"
          placeholder="Hi @{{name}}, check these offers"
        >{{ $introBody }}</textarea>
        <x-ui.field-error field="carousel_body" />
      </div>

      <p class="text-sm text-text-subtle">Add {{ $minCards }}–{{ $maxCards }} cards. Each card needs media (image/video upload), body (max {{ (int) config('templates.carousel_card_body_limit', 150) }} chars), and 1–2 buttons with matching types/order across cards.</p>
      <div id="carousel-cards-root" data-saved-cards='@json($cards)' class="flex flex-col gap-4"></div>
      <button type="button" id="add-carousel-card" class="fd-btn-sm mt-2 w-fit rounded border border-green-500 px-4 py-2 text-green-500">+ Add card</button>
      <x-ui.field-error field="cards" />
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>

  {{-- Legacy-parity guidelines popup (auto-opens on this step) --}}
  <div
    id="modal-carousel-guidelines"
    data-modal="carousel-guidelines"
    class="fixed inset-0 z-50 flex items-center justify-center bg-overlay p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-title-carousel-guidelines"
  >
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-[20px] bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
      <div class="flex shrink-0 items-start gap-4 border-b border-border-light bg-green-500 p-5 text-white">
        <div class="min-w-0 flex-1">
          <h2 id="modal-title-carousel-guidelines" class="text-xl font-bold leading-tight">
            Carousel Template Guidelines
          </h2>
        </div>
        <button type="button" data-modal-close class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/15 hover:bg-white/25" aria-label="Close">
          <span class="text-lg leading-none" aria-hidden="true">×</span>
        </button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto p-5 text-sm text-text-body">
        <ol class="flex list-none flex-col gap-4 p-0">
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">1</span>
            <div>
              <strong>Each card must include:</strong>
              Header, Body, and 1–2 Buttons.
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">2</span>
            <div>
              <strong>Button limit:</strong> Minimum <span class="font-semibold text-danger">1</span>, Maximum <span class="font-semibold text-danger">2</span> per card.
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">3</span>
            <div>
              <strong>If you choose this template to be a Carousel</strong>, the default Header, Footer, and Buttons will be removed from the main template.
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">4</span>
            <div class="flex flex-col gap-2">
              <div>
                <strong>All buttons across cards must have the same type and text.</strong>
              </div>
              <div class="rounded-lg bg-muted-surface p-3 text-xs text-text-subtle">
                <p class="mb-2">The system uses the button type you select:</p>
                <ul class="mb-3 list-disc space-y-1 pl-4">
                  <li>Choose <strong>Call Phone</strong> for phone numbers (<code>PHONE_NUMBER</code>).</li>
                  <li>Choose <strong>Visit Website</strong> for links (<code>URL</code>).</li>
                  <li>Choose <strong>Quick Reply</strong> for reply chips.</li>
                </ul>
                <p class="mb-1 font-semibold text-text-body">Example:</p>
                <ul class="mb-3 space-y-2 pl-1">
                  <li>
                    Card 1 Buttons:<br>
                    ➤ Call to Action – <code>Visit</code> – <code>https://example.com</code><br>
                    ➤ Quick Reply – <code>Help</code>
                  </li>
                  <li>
                    Card 2 Buttons (same sequence required):<br>
                    ➤ Call to Action – <code>Visit</code> – <code>https://example1.com</code><br>
                    ➤ Quick Reply – <code>Help</code>
                  </li>
                </ul>
                <p class="font-semibold text-danger">
                  Buttons must appear in the same order across all cards with matching text and types.
                  Mismatched button type, label, or sequence can lead to template rejection by WhatsApp.
                </p>
              </div>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">5</span>
            <div>
              <strong>Keep all card headers consistent in style.</strong>
              <p class="mt-1 text-text-subtle">
                If one card uses an image header, all cards should use an image. Don’t mix image and video headers — this can lead to template rejection.
              </p>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">6</span>
            <div>
              <strong>Always use predefined variables.</strong>
              Don’t add unnecessary variables manually; otherwise the data will not be sent to those variables.
            </div>
          </li>
          <li class="flex gap-3">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-muted-surface text-xs font-bold text-text-subtle">↻</span>
            <div>
              <strong>Need a quick reminder?</strong> Click the 📘 Guidelines button anytime to view these again.
            </div>
          </li>
        </ol>
      </div>

      <div class="flex shrink-0 justify-end border-t border-border-light p-4">
        <button type="button" data-modal-close class="fd-btn rounded-lg bg-green-500 px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
          Got it!
        </button>
      </div>
    </div>
  </div>
</x-templates.builder-layout>
