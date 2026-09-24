@php
  use App\Support\WhatsappMediaRules;

  $headerType = old('header_type', $payload['header']['type'] ?? 'none');
  $mediaPath = $payload['header']['media_path'] ?? null;
  $mediaUrl = $payload['header']['media_url'] ?? null;
  $useUrl = (bool) old('use_url', $payload['header']['use_url'] ?? false);
  $previewUrl = $mediaPath ? url('storage/'.$mediaPath) : $mediaUrl;
  $previousStepUrl = $previousStepUrl ?? route('templates.index');

  $imageMaxBytes = (int) config('templates.header_image_max', WhatsappMediaRules::maxKb('image') * 1024);
  $videoMaxBytes = (int) config('templates.header_video_max', WhatsappMediaRules::maxKb('video') * 1024);
  $documentMaxBytes = (int) config('templates.header_document_max', WhatsappMediaRules::maxKb('document') * 1024);
  $audioMaxBytes = (int) config('templates.header_audio_max', WhatsappMediaRules::maxKb('audio') * 1024);
@endphp

<x-templates.builder-layout
  active="header"
  :template="$template"
  :payload="$payload"
  :preview-data="$previewData ?? null"
  :builder-steps="$builderSteps ?? null"
>
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
    <input type="hidden" name="media_path" value="{{ old('media_path', $mediaPath ?? '') }}">

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
      <x-ui.field-error field="header_type" />
    </div>

    <div data-header-section="text" @class(['flex w-full max-w-[420px] flex-col gap-3', 'hidden' => $headerType !== 'text'])>
      <label for="header_text" class="fd-label">Header Text</label>
      <div data-validate-field>
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
    </div>

    <div data-header-section="image" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'image'])>
      <label class="fd-label">Image</label>
      <div data-header-url-wrap @class(['hidden' => ! $useUrl])>
        <div data-validate-field>
          <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/image.png" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('media_url') border-red-500 @enderror" data-header-url-input @disabled(! $useUrl || $headerType !== 'image')>
          <p class="mt-1 text-xs text-text-subtle">Use a public HTTPS image URL (JPEG/PNG).</p>
          <x-ui.field-error field="media_url" />
        </div>
      </div>
      <div data-header-file-wrap @class(['hidden' => $useUrl])>
        <x-templates.upload-zone
          id="header_image"
          accept="{{ WhatsappMediaRules::accept('image') }}"
          :hint="WhatsappMediaRules::hint('image')"
          :preview-url="$headerType === 'image' ? $previewUrl : null"
          :enabled="$headerType === 'image' && ! $useUrl"
          :max-bytes="$imageMaxBytes"
        />
        <x-ui.field-error field="header_media" />
        <p class="hidden text-xs text-red-500" data-header-upload-error></p>
      </div>
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" data-header-use-url @checked($useUrl && $headerType === 'image') @disabled($headerType !== 'image')>
        Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="video" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'video'])>
      <label class="fd-label">Video</label>
      <div data-header-url-wrap @class(['hidden' => ! $useUrl])>
        <div data-validate-field>
          <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/video.mp4" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('media_url') border-red-500 @enderror" data-header-url-input @disabled(! $useUrl || $headerType !== 'video')>
          <p class="mt-1 text-xs text-text-subtle">Use a public HTTPS video URL (MP4/3GP).</p>
          <x-ui.field-error field="media_url" />
        </div>
      </div>
      <div data-header-file-wrap @class(['hidden' => $useUrl])>
        <x-templates.upload-zone
          id="header_video"
          accept="{{ WhatsappMediaRules::accept('video') }}"
          :hint="WhatsappMediaRules::hint('video')"
          :preview-url="$headerType === 'video' ? $previewUrl : null"
          :enabled="$headerType === 'video' && ! $useUrl"
          :max-bytes="$videoMaxBytes"
        />
        <x-ui.field-error field="header_media" />
        <p class="hidden text-xs text-red-500" data-header-upload-error></p>
      </div>
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" data-header-use-url @checked($useUrl && $headerType === 'video') @disabled($headerType !== 'video')>
        Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="document" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'document'])>
      <label class="fd-label">Document</label>
      <div data-header-url-wrap @class(['hidden' => ! $useUrl])>
        <div data-validate-field>
          <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/doc.pdf" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('media_url') border-red-500 @enderror" data-header-url-input @disabled(! $useUrl || $headerType !== 'document')>
          <x-ui.field-error field="media_url" />
        </div>
      </div>
      <div data-header-file-wrap @class(['hidden' => $useUrl])>
        <x-templates.upload-zone
          id="header_document"
          accept="application/pdf,.pdf"
          :hint="'PDF — max '.WhatsappMediaRules::maxMbLabel('document')"
          :preview-url="$headerType === 'document' ? $previewUrl : null"
          :enabled="$headerType === 'document' && ! $useUrl"
          :max-bytes="$documentMaxBytes"
        />
        <x-ui.field-error field="header_media" />
        <p class="hidden text-xs text-red-500" data-header-upload-error></p>
      </div>
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" data-header-use-url @checked($useUrl && $headerType === 'document') @disabled($headerType !== 'document')>
        Use URL instead of uploading a file
      </label>
      <input type="text" name="doc_name" value="{{ old('doc_name', $payload['header']['doc_name'] ?? '') }}" placeholder="Document file name" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500" @disabled($headerType !== 'document')>
    </div>

    <div data-header-section="audio" @class(['flex w-full flex-col gap-3', 'hidden' => $headerType !== 'audio'])>
      <label class="fd-label">Audio</label>
      <div data-header-url-wrap @class(['hidden' => ! $useUrl])>
        <div data-validate-field>
          <input type="url" name="media_url" value="{{ old('media_url', $mediaUrl ?? '') }}" placeholder="https://example.com/audio.mp3" class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('media_url') border-red-500 @enderror" data-header-url-input @disabled(! $useUrl || $headerType !== 'audio')>
          <x-ui.field-error field="media_url" />
        </div>
      </div>
      <div data-header-file-wrap @class(['hidden' => $useUrl])>
        <x-templates.upload-zone
          id="header_audio"
          accept="{{ WhatsappMediaRules::accept('audio') }}"
          :hint="WhatsappMediaRules::hint('audio')"
          :preview-url="$headerType === 'audio' ? $previewUrl : null"
          :enabled="$headerType === 'audio' && ! $useUrl"
          :max-bytes="$audioMaxBytes"
        />
        <x-ui.field-error field="header_media" />
        <p class="hidden text-xs text-red-500" data-header-upload-error></p>
      </div>
      <label class="flex items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="use_url" value="1" data-header-use-url @checked($useUrl && $headerType === 'audio') @disabled($headerType !== 'audio')>
        Use URL instead of uploading a file
      </label>
    </div>

    <div data-header-section="location" @class(['rounded-xl border border-border bg-muted-surface p-4 text-sm text-text-subtle', 'hidden' => $headerType !== 'location'])>
      Location header will use the customer's location when the message is sent.
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl" />
  </form>
</x-templates.builder-layout>
