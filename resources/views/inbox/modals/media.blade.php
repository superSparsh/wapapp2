@php $modal = config('inbox-modals.media'); @endphp
<x-inbox.modal id="media" :title="$modal['title']" :subtitle="$modal['subtitle']" :open="$open ?? false">
    <x-inbox.modal-form>
        <form class="flex flex-col gap-6" data-inbox-media-form enctype="multipart/form-data">
            <div class="flex flex-col gap-2">
                <x-form.label for="media_type">Media Type <span class="text-red-500">*</span></x-form.label>
                <select
                    id="media_type"
                    name="media_type"
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
                    accept="image/*,video/*,audio/*,.pdf,.doc,.docx"
                    class="w-full rounded-xl border border-border bg-elevated px-3.5 py-3 text-sm"
                    required
                >
            </div>
            <x-form.input id="media_caption" name="caption" placeholder="Add a caption (optional)">
                <x-slot:label>Caption</x-slot:label>
            </x-form.input>
            <p class="hidden text-xs text-red-500" data-inbox-media-error></p>
            <x-inbox.modal-actions submit="Send Media" />
        </form>
    </x-inbox.modal-form>
    <x-inbox.phone-preview />
</x-inbox.modal>
