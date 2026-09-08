@props(['open' => false, 'closeHref' => null, 'botId' => null])

<div
  id="modal-add-sources"
  data-modal="add-sources"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-add-sources"
>
  <div class="flex max-h-[90vh] w-full max-w-[769px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-add-sources" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Add Knowledge Source
        </h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Add text, documents, or URLs to teach your bot about your business.
        </p>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @else
        <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      @endif
    </div>

    <form action="{{ route('openai-key.business-info.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
      @csrf
      @if ($botId)
        <input type="hidden" name="ai_bot_id" value="{{ $botId }}">
      @endif

      <div class="w-full overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-6">
          <div class="flex flex-col gap-2">
            <label for="source_title" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Title<span class="text-[red]">*</span>
            </label>
            <input
              id="source_title"
              name="title"
              type="text"
              required
              value="{{ old('title') }}"
              placeholder="Enter a title for this source"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>

          <div class="flex flex-col gap-2">
            <label for="source_content_type" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Content Type
            </label>
            <div class="relative">
              <select
                id="source_content_type"
                name="content_type"
                class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="text" @selected(old('content_type', 'text') === 'text')>Text</option>
                <option value="url" @selected(old('content_type') === 'url')>URL</option>
                <option value="document" @selected(old('content_type') === 'document')>Document (upload)</option>
              </select>
              <img
                src="{{ asset('images/commerce/arrow-down.svg') }}"
                alt=""
                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                width="16"
                height="16"
              >
            </div>
          </div>

          <div id="content-field" class="flex flex-col gap-2">
            <label for="source_content" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Content / URL
            </label>
            <textarea
              id="source_content"
              name="content"
              rows="5"
              placeholder="Enter text content or a URL (https://...)"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >{{ old('content') }}</textarea>
          </div>

          <div id="file-field" class="flex w-full flex-col gap-3">
            <p class="text-sm font-semibold leading-[1.4] text-text-primary">Upload Document</p>
            <label
              for="source_file"
              class="flex h-[88px] w-full cursor-pointer flex-col items-center justify-center rounded-md border border-dashed border-border bg-elevated px-[68px] py-3"
            >
              <div class="flex w-[206px] flex-col items-center gap-2">
                <img src="{{ asset('images/openai-key/upload.svg') }}" alt="" class="size-6" width="24" height="24">
                <div class="w-full text-center text-text-body">
                  <p class="text-xs font-medium leading-normal">
                    Drag &amp; Drop or<span class="text-green-500"> choose</span> file to upload
                  </p>
                  <p class="mt-1 text-[10px] font-medium leading-normal opacity-50">
                    Supports .txt, .pdf, .doc, .docx (max 10MB)
                  </p>
                </div>
              </div>
              <input id="source_file" name="file" type="file" accept=".txt,.pdf,.doc,.docx" class="sr-only">
            </label>
          </div>
        </div>
      </div>

      <div class="flex w-full items-center justify-between">
        @if (! empty($closeHref))
          <a
            href="{{ $closeHref }}"
            class="fd-btn inline-flex items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </a>
        @else
          <button
            type="button"
            data-modal-close
            class="fd-btn inline-flex items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </button>
        @endif
        <button
          type="submit"
          class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          Add Source
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (function () {
    var contentType = document.getElementById('source_content_type');
    var contentField = document.getElementById('content-field');
    var fileField = document.getElementById('file-field');
    if (!contentType) return;

    function toggleFields() {
      var val = contentType.value;
      contentField.style.display = val === 'document' ? 'none' : '';
      fileField.style.display = val === 'document' ? '' : 'none';
    }
    contentType.addEventListener('change', toggleFields);
    toggleFields();
  })();
</script>
