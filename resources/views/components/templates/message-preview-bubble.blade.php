@props([
    'size' => 'default',
    'previewData' => null,
    'live' => false,
    'scrollBody' => true,
])

@php
  use App\Domains\Templates\Support\WhatsAppTextFormatter;

  $isCompact = $size === 'compact';
  $bodyText = $previewData['body'] ?? 'Select a template to preview the message.';
  $bodyHtml = WhatsAppTextFormatter::toHtml($bodyText);
  $footerText = $previewData['footer'] ?? '';
  $headerType = $previewData['header_type'] ?? 'none';
  $headerText = $previewData['header_text'] ?? '';
  $buttons = $previewData['buttons'] ?? [];
  $headerImage = $previewData['header_image'] ?? null;
  $headerVideo = $previewData['header_video'] ?? $headerImage;
  $headerDocument = $previewData['header_document'] ?? null;
  $headerAudio = $previewData['header_audio'] ?? null;
  $headerDocumentName = trim((string) ($previewData['header_document_name'] ?? $previewData['doc_name'] ?? ''));
  if ($headerDocumentName === '' && filled($headerDocument)) {
      $headerDocumentName = basename(parse_url((string) $headerDocument, PHP_URL_PATH) ?: '') ?: 'Document';
  }
  $headerMediaVariable = trim((string) ($previewData['header_media_variable'] ?? ''));
  $showHeaderVariable = $headerMediaVariable !== '' && in_array($headerType, ['image', 'video', 'document', 'audio'], true);
  $showHeaderImage = $headerType === 'image' && filled($headerImage) && ! $showHeaderVariable;
  $showHeaderVideo = $headerType === 'video' && filled($headerVideo) && ! $showHeaderVariable;
  $showHeaderDocument = $headerType === 'document' && ! $showHeaderVariable && (filled($headerDocument) || $headerDocumentName !== '');
  $showHeaderAudio = $headerType === 'audio' && filled($headerAudio) && ! $showHeaderVariable;
  $showHeaderText = in_array($headerType, ['text', 'location'], true) && $headerText !== '';
  $carouselCards = is_array($previewData['carousel_cards'] ?? null) ? $previewData['carousel_cards'] : [];
  $isCarousel = (bool) ($previewData['is_carousel'] ?? false) || $carouselCards !== [];
  // Cap the whole message card (header + body + buttons) so long templates scroll inside 500px.
  $bubbleScrollable = (bool) $scrollBody && ! $isCarousel;
  $ltoOffer = is_array($previewData['lto_offer'] ?? null) ? $previewData['lto_offer'] : null;
  $showLtoOffer = is_array($ltoOffer) && filled($ltoOffer['introduction'] ?? null);
@endphp

<div
  @if ($live) id="template-live-preview" @endif
  @class([
    'mx-auto flex w-full flex-col gap-3 rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-solid border-border bg-elevated p-2',
    'mt-[205px] max-w-[354px]' => ! $isCompact,
    'mt-[160px] max-w-full' => $isCompact,
    'max-h-[500px] overflow-x-hidden overflow-y-auto overscroll-contain' => $bubbleScrollable,
  ])
>
  <div data-preview-standard @class(['contents' => ! $isCarousel, 'hidden' => $isCarousel])>
    <div
      data-preview-header-variable
      @class([
        'flex min-h-[80px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border bg-muted-surface px-3 py-4 text-center',
        'hidden' => ! $showHeaderVariable || $isCarousel,
      ])
    >
      <span class="text-xs font-semibold uppercase tracking-wide text-text-subtle">{{ ucfirst($headerType) }} variable</span>
      <span data-preview-header-variable-label class="break-all text-sm font-medium text-text-body">{{ $showHeaderVariable ? $headerMediaVariable : '' }}</span>
    </div>
    <img
      data-preview-header-image
      src="{{ $showHeaderImage ? $headerImage : '' }}"
      alt=""
      @class([
        'h-auto max-h-[250px] w-full rounded object-cover',
        'hidden' => ! $showHeaderImage || $isCarousel,
      ])
      width="100%"
      height="220"
    >
    <video
      data-preview-header-video
      src="{{ $showHeaderVideo ? $headerVideo : '' }}"
      @class([
        'max-h-40 w-full rounded object-cover',
        'hidden' => ! $showHeaderVideo || $isCarousel,
      ])
      controls
      playsinline
    ></video>
    <a
      data-preview-header-document
      href="{{ $showHeaderDocument && filled($headerDocument) ? $headerDocument : '#' }}"
      @if ($showHeaderDocument && filled($headerDocument))
        target="_blank"
        rel="noopener noreferrer"
      @else
        aria-disabled="true"
      @endif
      @class([
        'flex w-full items-center gap-3 rounded-lg border border-border bg-muted-surface px-3 py-2.5 no-underline',
        'pointer-events-none opacity-70' => ! filled($headerDocument),
        'hover:border-green-500' => filled($headerDocument),
        'hidden' => ! $showHeaderDocument || $isCarousel,
      ])
    >
      <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-elevated text-xs font-semibold text-green-600">PDF</span>
      <span class="min-w-0 flex-1">
        <span data-preview-header-document-name class="block truncate text-sm font-medium text-link-green">
          {{ $headerDocumentName !== '' ? $headerDocumentName : 'Document' }}
        </span>
        <span class="block text-[11px] text-text-subtle">Tap to open</span>
      </span>
    </a>
    <audio
      data-preview-header-audio
      src="{{ $showHeaderAudio ? $headerAudio : '' }}"
      @class([
        'w-full',
        'hidden' => ! $showHeaderAudio || $isCarousel,
      ])
      controls
      preload="metadata"
    ></audio>
    <p
      data-preview-header-text
      @class([
        'w-full text-base font-semibold leading-[1.4] text-text-body',
        'hidden' => ! $showHeaderText || $isCarousel,
      ])
    >{{ $headerText }}</p>
  </div>

  <div
    data-preview-lto
    @class([
      'flex w-full items-stretch gap-2 rounded-md bg-[#f2f2f2] px-2 py-1.5',
      'hidden' => ! $showLtoOffer || $isCarousel,
    ])
  >
    <div class="flex w-[20%] shrink-0 items-center justify-center text-lg text-text-body" aria-hidden="true">🎁</div>
    <div class="flex min-w-0 flex-1 flex-col items-start justify-center text-[11px] leading-snug text-text-body">
      <span data-preview-lto-intro class="font-medium">{{ $showLtoOffer ? ($ltoOffer['introduction'] ?? '') : '' }}</span>
      <span data-preview-lto-code class="mt-0.5">Code : {{ $showLtoOffer ? ($ltoOffer['coupon_code'] ?? '$(offer_code)') : '$(offer_code)' }}</span>
      <span
        data-preview-lto-expiration
        @class(['mt-0.5', 'hidden' => ! ($showLtoOffer && ($ltoOffer['show_expiration'] ?? false))])
      >{{ $ltoOffer['expiration_label'] ?? '$(offerExpirationTimeMs) End' }}</span>
    </div>
  </div>

  <div
    data-preview-body
    @class([
      'wa-preview-body w-full font-normal leading-[1.4] text-text-body break-words',
      'text-base' => ! $isCompact,
      'text-sm' => $isCompact,
    ])
  >{!! $bodyHtml !== '' ? $bodyHtml : e($bodyText) !!}</div>

  <p
    data-preview-footer
    @class([
      'w-full text-xs font-normal leading-[1.4] text-text-subtle',
      'hidden' => $footerText === '' || $isCarousel,
    ])
  >{{ $footerText }}</p>

  <img
    data-preview-divider
    src="{{ asset('images/templates/message-divider.svg') }}"
    alt=""
    @class([
      'block w-full',
      'hidden' => count($buttons) === 0 || $isCarousel,
    ])
    width="338"
    height="1"
  >
  <div data-preview-buttons @class(['flex w-full flex-col', 'hidden' => $isCarousel])>
    @foreach ($buttons as $button)
      @php
        $buttonType = strtolower((string) ($button['type'] ?? 'url'));
        $icon = match ($buttonType) {
          'phone', 'phone_number' => 'call.svg',
          'quick_reply' => 'quick-reply.svg',
          'flow' => 'flow.svg',
          'copy_code' => 'quick-reply.svg',
          default => 'export.svg',
        };
      @endphp
      <div class="flex w-full items-center justify-center gap-2 py-1">
        <img src="{{ asset('images/templates/' . $icon) }}" alt="" class="size-4 shrink-0" width="16" height="16">
        <span @class([
          'font-medium leading-[1.4] whitespace-nowrap text-link-green',
          'text-base' => ! $isCompact,
          'text-sm' => $isCompact,
        ])>{{ $button['text'] ?? 'Button' }}</span>
      </div>
    @endforeach
  </div>

  {{-- WhatsApp-style carousel cards (legacy parity) --}}
  <div
    data-preview-carousel
    @class([
      'wa-carousel-preview w-full',
      'hidden' => ! $isCarousel || $carouselCards === [],
    ])
  >
    <div data-preview-carousel-track class="wa-carousel-track flex gap-2 overflow-x-auto pb-1">
      @foreach ($carouselCards as $card)
        @php
          $cardHeader = strtoupper((string) ($card['header'] ?? $card['header_type'] ?? 'IMAGE'));
          $cardMedia = $card['media_url'] ?? $card['header_media'] ?? $card['url'] ?? null;
          $cardBody = (string) ($card['body'] ?? $card['body_text'] ?? '');
          $cardButtons = is_array($card['buttons'] ?? null) ? $card['buttons'] : [];
        @endphp
        <div class="wa-carousel-card flex w-[200px] shrink-0 flex-col overflow-hidden rounded-lg border border-border bg-white">
          @if ($cardHeader === 'VIDEO' && filled($cardMedia))
            <video src="{{ $cardMedia }}" class="aspect-video w-full object-cover" muted playsinline preload="metadata"></video>
          @elseif (filled($cardMedia))
            <img src="{{ $cardMedia }}" alt="" class="aspect-video w-full object-cover">
          @else
            <div class="flex aspect-video w-full items-center justify-center bg-muted-surface text-[11px] text-text-muted">
              {{ $cardHeader === 'VIDEO' ? 'Video' : 'Image' }}
            </div>
          @endif
          <div class="flex flex-1 flex-col gap-2 p-2">
            <p class="wa-preview-body line-clamp-3 text-xs leading-[1.4] text-text-body">
              {!! $cardBody !== '' ? WhatsAppTextFormatter::toHtml($cardBody) : e('Card body') !!}
            </p>
            @if ($cardButtons !== [])
              <div class="mt-auto flex flex-col border-t border-border/60 pt-1">
                @foreach ($cardButtons as $cardButton)
                  @continue(! is_array($cardButton))
                  @php
                    $btnText = trim((string) ($cardButton['text'] ?? $cardButton['title'] ?? ''));
                    $btnType = strtolower((string) ($cardButton['type'] ?? 'quick_reply'));
                    $btnIcon = match ($btnType) {
                      'phone', 'phone_number' => 'call.svg',
                      'quick_reply' => 'quick-reply.svg',
                      default => 'export.svg',
                    };
                  @endphp
                  @continue($btnText === '')
                  <div class="flex items-center justify-center gap-1.5 py-1">
                    <img src="{{ asset('images/templates/' . $btnIcon) }}" alt="" class="size-3.5 shrink-0" width="14" height="14">
                    <span class="truncate text-[11px] font-medium text-link-green">{{ $btnText }}</span>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
