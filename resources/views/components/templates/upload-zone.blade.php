@props([
    'hint' => 'Only .png, .jpg, .mp4 or .3gp',
    'name' => 'header_media',
    'id' => 'header_media',
    'accept' => 'image/png,image/jpeg,video/mp4,video/3gpp',
    'previewUrl' => null,
])

<div class="flex flex-col gap-3" data-header-upload>
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
    name="{{ $name }}"
    id="{{ $id }}"
    accept="{{ $accept }}"
    class="sr-only"
    data-header-media-input
  >
  <div data-header-media-preview @class(['hidden' => blank($previewUrl)])>
    <img
      src="{{ $previewUrl }}"
      alt=""
      class="max-h-40 w-full rounded-lg object-cover"
      data-header-media-image
      @if (blank($previewUrl)) hidden @endif
    >
    <video
      @class(['max-h-40 w-full rounded-lg', 'hidden' => true])
      controls
      data-header-media-video
    ></video>
    <p class="mt-1 text-xs text-text-subtle" data-header-media-name></p>
  </div>
</div>
