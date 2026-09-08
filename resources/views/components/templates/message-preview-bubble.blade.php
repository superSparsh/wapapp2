@props([
    'size' => 'default',
    'previewData' => null,
    'live' => false,
    'scrollBody' => false,
])

@php
  $isCompact = $size === 'compact';
  $bodyText = $previewData['body'] ?? 'Select a template to preview the message.';
  $footerText = $previewData['footer'] ?? '';
  $headerType = $previewData['header_type'] ?? 'none';
  $headerText = $previewData['header_text'] ?? '';
  $buttons = $previewData['buttons'] ?? [];
  $headerImage = $previewData['header_image'] ?? null;
  $showHeaderImage = $headerType === 'image' && filled($headerImage);
  $showHeaderVideo = $headerType === 'video' && filled($previewData['header_video'] ?? $headerImage);
  $showHeaderText = in_array($headerType, ['text', 'location'], true) && $headerText !== '';
@endphp

<div
  @if ($live) id="template-live-preview" @endif
  @class([
    'mx-auto flex w-full flex-col gap-3 rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-solid border-border bg-elevated p-2',
    'mt-[205px] max-w-[354px]' => ! $isCompact,
    'mt-[150px] max-w-full' => $isCompact,
  ])
>
  <img
    data-preview-header-image
    src="{{ $showHeaderImage ? $headerImage : '' }}"
    alt=""
    @class([
      'aspect-[1600/800] w-full rounded object-cover',
      'hidden' => ! $showHeaderImage,
    ])
    width="338"
    height="169"
  >
  <video
    data-preview-header-video
    src="{{ $showHeaderVideo ? $headerImage : '' }}"
    @class([
      'max-h-40 w-full rounded object-cover',
      'hidden' => ! $showHeaderVideo,
    ])
    controls
    playsinline
  ></video>
  <p
    data-preview-header-text
    @class([
      'w-full text-base font-semibold leading-[1.4] text-text-body',
      'hidden' => ! $showHeaderText,
    ])
  >{{ $headerText }}</p>
  <div
    data-preview-body
    @class([
      'w-full whitespace-pre-wrap font-normal leading-[1.4] text-text-body',
      'text-base' => ! $isCompact,
      'text-sm' => $isCompact,
      'h-[450px] overflow-y-scroll' => $scrollBody,
    ])
  >{{ $bodyText }}</div>
  <p
    data-preview-footer
    @class([
      'w-full text-xs font-normal leading-[1.4] text-text-subtle',
      'hidden' => $footerText === '',
    ])
  >{{ $footerText }}</p>
  <img
    data-preview-divider
    src="{{ asset('images/templates/message-divider.svg') }}"
    alt=""
    @class([
      'block w-full',
      'hidden' => count($buttons) === 0,
    ])
    width="338"
    height="1"
  >
  <div data-preview-buttons class="flex w-full flex-col">
    @foreach ($buttons as $button)
      @php
        $buttonType = $button['type'] ?? 'url';
        $icon = match ($buttonType) {
            'phone' => 'call.svg',
            'quick_reply' => 'quick-reply.svg',
            'flow' => 'flow.svg',
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
</div>
