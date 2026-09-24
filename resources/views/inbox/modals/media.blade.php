@php
  $modal = config('inbox-modals.media');
  $mediaRules = \App\Support\WhatsappMediaRules::clientConfig();
@endphp
<x-inbox.modal id="media" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form
            class="flex flex-col gap-6"
            data-inbox-media-form
            enctype="multipart/form-data"
            data-media-rules='@json($mediaRules)'
        >
            <div class="flex flex-col gap-2">
                <x-form.label for="media_type">Media Type <span class="text-red-500">*</span></x-form.label>
                <select
                    id="media_type"
                    name="media_type"
                    data-inbox-media-type
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    required
                >
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                    <option value="document">Document</option>
                    <option value="audio">Audio</option>
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <x-form.label for="media_file">File <span class="text-red-500">*</span></x-form.label>
                <input
                    id="media_file"
                    name="file"
                    type="file"
                    data-inbox-media-file
                    accept="{{ \App\Support\WhatsappMediaRules::accept('image') }}"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm"
                    required
                >
                <p class="text-[10px] text-text-subtle" data-inbox-media-hint>{{ \App\Support\WhatsappMediaRules::hint('image') }}</p>
            </div>
            <p class="hidden text-xs text-red-500" data-inbox-media-error></p>
            <x-inbox.modal-actions submit="Send Media" />
        </form>
    </x-inbox.modal-form>

    <x-inbox.phone-preview title="Media Preview" subtitle="Preview how the media will appear">
        <x-slot:preview>
            <div
                class="mx-auto flex w-full max-w-[354px] mt-5 flex-col gap-2 rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-border bg-elevated p-2"
                data-inbox-media-preview
            >
                <div
                    class="flex min-h-[160px] items-center justify-center rounded bg-muted-surface text-center text-xs text-text-subtle"
                    data-inbox-media-preview-empty
                >
                    Choose a file to preview
                </div>
                <img
                    data-inbox-media-preview-image
                    src=""
                    alt=""
                    class="hidden max-h-64 w-full rounded object-cover"
                >
                <video
                    data-inbox-media-preview-video
                    src=""
                    class="hidden max-h-64 w-full rounded object-cover"
                    controls
                    playsinline
                ></video>
                <audio
                    data-inbox-media-preview-audio
                    src=""
                    class="hidden w-full"
                    controls
                ></audio>
                <div
                    data-inbox-media-preview-document
                    class="hidden items-center gap-2 rounded bg-muted-surface p-3 text-sm text-text-body"
                >
                    <span class="text-lg" aria-hidden="true">📄</span>
                    <span class="min-w-0 truncate font-medium" data-inbox-media-preview-filename></span>
                </div>
            </div>
        </x-slot:preview>
    </x-inbox.phone-preview>
</x-inbox.modal>
