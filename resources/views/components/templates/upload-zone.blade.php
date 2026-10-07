@props([
    'hint' => 'Only .png, .jpg, .mp4 or .3gp',
    'name' => 'header_media',
    'id' => 'header_media',
    'accept' => 'image/png,image/jpeg,video/mp4,video/3gpp',
    'previewUrl' => null,
    'fileName' => null,
    'previewKind' => 'image',
    'enabled' => true,
    'maxBytes' => null,
])

@php
  $hasPreview = filled($previewUrl) || filled($fileName);
  $kind = in_array($previewKind, ['image', 'video', 'document', 'audio'], true) ? $previewKind : 'image';
@endphp

<div class="flex flex-col gap-3" data-header-upload @if ($maxBytes) data-max-bytes="{{ (int) $maxBytes }}" @endif>
  <label
    for="{{ $id }}"
    class="flex h-[88px] cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-divider px-[68px] py-3 transition-colors hover:border-green-500"
  >
    <img src="{{ asset('images/templates/upload-frame.svg') }}" alt="" class="size-6" aria-hidden="true">
    <p class="mt-2 text-center text-xs text-text-body">
      <span class="font-medium">Drag &amp; Drop or</span>
      <span class="font-medium text-green-500"> choose</span>
      <span class="font-medium"> file to upload</span>
    </p>
    <p class="mt-1 text-center text-[10px] font-medium text-text-body opacity-50">{{ $hint }}</p>
  </label>
  <input
    type="file"
    name="{{ $enabled ? $name : '' }}"
    id="{{ $id }}"
    accept="{{ $accept }}"
    class="sr-only"
    data-header-media-input
    @disabled(! $enabled)
  >
  <div
    data-header-media-preview
    @if (filled($previewUrl)) data-preview-url="{{ $previewUrl }}" @endif
    @class([
      'flex items-start gap-3 rounded-lg border border-border bg-muted-surface p-3',
      'hidden' => ! $hasPreview,
    ])
  >
    <div class="min-w-0 flex-1">
      <p class="text-sm font-medium leading-5 text-text-body break-all" data-header-media-name>
        {{ $fileName ?: '' }}
      </p>
      <p class="mt-1 text-xs text-text-subtle" data-header-media-status>
        {{ $hasPreview ? 'Uploaded' : '' }}
      </p>
    </div>
    <div class="w-36 shrink-0">
      <img
        src="{{ $kind === 'image' && filled($previewUrl) ? $previewUrl : '' }}"
        alt=""
        class="max-h-28 w-full rounded-md object-cover"
        data-header-media-image
        @if ($kind === 'image' && filled($previewUrl)) data-preview-url="{{ $previewUrl }}" @endif
        @if ($kind !== 'image' || blank($previewUrl)) hidden @endif
      >
      <video
        @class(['max-h-28 w-full rounded-md', 'hidden' => $kind !== 'video' || blank($previewUrl)])
        @if ($kind === 'video' && filled($previewUrl))
          src="{{ $previewUrl }}"
          data-preview-url="{{ $previewUrl }}"
        @endif
        controls
        muted
        playsinline
        preload="metadata"
        data-header-media-video
      ></video>
      <audio
        @class(['w-full', 'hidden' => $kind !== 'audio' || blank($previewUrl)])
        @if ($kind === 'audio' && filled($previewUrl))
          src="{{ $previewUrl }}"
          data-preview-url="{{ $previewUrl }}"
        @endif
        controls
        preload="metadata"
        data-header-media-audio
      ></audio>
      @if ($kind === 'document')
        <a
          href="{{ filled($previewUrl) ? $previewUrl : '#' }}"
          @if (filled($previewUrl)) target="_blank" rel="noopener noreferrer" @endif
          class="flex h-16 items-center justify-center rounded-md border border-dashed border-divider bg-elevated px-2 text-center text-xs font-medium text-link-green hover:border-green-500"
          data-header-media-document
          @if (filled($previewUrl)) data-preview-url="{{ $previewUrl }}" @endif
        >
          {{ filled($fileName) ? $fileName : 'Document' }}
        </a>
      @endif
    </div>
  </div>
</div>
