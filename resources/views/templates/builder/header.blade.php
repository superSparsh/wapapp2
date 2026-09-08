@php
  $headerType = old('header_type', $payload['header']['type'] ?? 'none');
  $mediaPath = $payload['header']['media_path'] ?? null;
  $mediaUrl = $payload['header']['media_url'] ?? null;
  $useUrl = old('use_url', $payload['header']['use_url'] ?? false);
  $previewUrl = $mediaPath ? asset('storage/'.$mediaPath) : $mediaUrl;
@endphp

<x-templates.builder-layout active="header" :template="$template" :payload="$payload" :preview-data="$previewData ?? null">
  <form
    id="builder-header-form"
    method="post"
    action="{{ route('templates.builder.header.save', $template) }}"
    enctype="multipart/form-data"
    class="flex flex-col gap-4"
    data-header-upload-url="{{ route('templates.builder.header.media', $template) }}"
    data-validate-form
  >
    @csrf

    <div class="flex flex-col gap-2">
      <p class="text-sm font-semibold leading-[1.5] text-text-body">Type</p>
      <div class="flex flex-wrap items-start gap-5">
        @foreach ([
          ['none', 'None', $headerType === 'none'],
          ['text', 'Header text', $headerType === 'text'],
          ['image', 'Image', $headerType === 'image'],
          ['video', 'Video', $headerType === 'video'],
          ['document', 'Document', $headerType === 'document'],
          ['audio', 'Audio', $headerType === 'audio'],
          ['location', 'Location', $headerType === 'location'],
        ] as [$value, $label, $checked])
          <label class="flex cursor-pointer items-center gap-2">
            <input type="radio" name="header_type" value="{{ $value }}" class="sr-only" @checked($checked)>
            <img
              src="{{ asset('images/templates/' . ($checked ? 'radio-checked' : 'radio-unchecked') . '.svg') }}"
              alt=""
              class="size-4 shrink-0 header-type-icon"
              width="16"
              height="16"
              data-checked-src="{{ asset('images/templates/radio-checked.svg') }}"
              data-unchecked-src="{{ asset('images/templates/radio-unchecked.svg') }}"
            >
            <span @class([
              'text-base font-medium leading-[1.5] whitespace-nowrap header-type-label',
              'text-green-500' => $checked,
              'text-blue-200' => ! $checked,
            ])>{{ $label }}</span>
          </label>
        @endforeach
      </div>
      <p class="text-xs text-text-subtle">Header is optional. Choose None to skip this step.</p>
    </div>

    <div data-header-section="text" @class(['flex w-full max-w-[420px] flex-col gap-3', 'hidden' => ! in_array($headerType, ['text', 'location'], true)])>
      <label for="header_text" class="fd-label">Header Text</label>
      <input
        id="header_text"
        name="header_text"
        type="text"
        value="{{ old('header_text', $payload['header']['text'] ?? '') }}"
        placeholder="Write headline"
        class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('header_text') border-red-500 @enderror"
      >
      <x-ui.field-error field="header_text" />
    </div>

    <div data-header-section="image" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'image'])>
      <label class="fd-label">Image</label>
      @if ($useUrl)
        <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/image.png" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
      @else
        <x-templates.upload-zone :preview-url="$previewUrl" accept="image/png,image/jpeg" hint="Only .png or .jpg (max 5 MB)" />
      @endif
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" @checked($useUrl)> Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="video" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'video'])>
      <label class="fd-label">Video</label>
      @if ($useUrl)
        <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/video.mp4" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
      @else
        <x-templates.upload-zone id="header_video" accept="video/mp4,video/3gpp" hint="Only .mp4 or .3gp (max 16 MB)" :preview-url="$previewUrl" />
      @endif
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" @checked($useUrl)> Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="document" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'document'])>
      <label class="fd-label">Document</label>
      @if ($useUrl)
        <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/doc.pdf" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
      @else
        <x-templates.upload-zone id="header_document" accept="application/pdf" hint="Only .pdf (max 10 MB)" :preview-url="$previewUrl" />
      @endif
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" @checked($useUrl)> Use URL instead of uploading a file
      </label>
      <input type="text" name="doc_name" value="{{ old('doc_name', $payload['header']['doc_name'] ?? '') }}" placeholder="Document file name" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
    </div>

    <div data-header-section="audio" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'audio'])>
      <label class="fd-label">Audio</label>
      @if ($useUrl)
        <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/audio.mp3" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
      @else
        <x-templates.upload-zone id="header_audio" accept="audio/mpeg,audio/wav,audio/aac,audio/ogg,audio/mp4" hint=".mp3, .wav, .aac, .ogg, .m4a (max 16 MB)" :preview-url="$previewUrl" />
      @endif
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" @checked($useUrl)> Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="location" @class(['rounded-xl border border-border bg-muted-surface p-4 text-sm text-text-subtle', 'hidden' => $headerType !== 'location'])>
      Location header will use the customer's location when the message is sent.
    </div>

    <div class="flex w-full items-center justify-end">
      <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-2 text-primary-2 transition-opacity hover:opacity-90">
        Next
      </button>
    </div>
  </form>
</x-templates.builder-layout>
