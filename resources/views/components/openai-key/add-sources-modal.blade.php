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
  data-bot-id="{{ $botId }}"
  data-scrape-url="{{ route('openai-key.knowledge-base.scrape') }}"
  data-extract-url="{{ route('openai-key.knowledge-base.extract') }}"
  data-structure-url="{{ route('openai-key.knowledge-base.structure') }}"
  data-add-url="{{ route('openai-key.knowledge-base.add-manual') }}"
  data-csrf="{{ csrf_token() }}"
>
  <div class="flex max-h-[90vh] w-full max-w-[769px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-add-sources" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Add Knowledge Source
        </h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Upload a document, paste text, or add a website URL. Content is indexed for your bot answers.
        </p>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @endif
    </div>

    <div id="kb-add-step-input" class="flex flex-col gap-4">
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold text-text-primary">Source type</label>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3" role="tablist" aria-label="Source type">
          <button type="button" data-kb-type="text" class="kb-type-btn rounded-xl border border-border bg-elevated px-3 py-3 text-left transition-colors hover:border-green-500">
            <span class="block text-sm font-semibold text-text-primary">Manual text</span>
            <span class="mt-0.5 block text-[11px] text-text-muted">Paste notes or FAQs</span>
          </button>
          <button type="button" data-kb-type="url" class="kb-type-btn rounded-xl border border-border bg-elevated px-3 py-3 text-left transition-colors hover:border-green-500">
            <span class="block text-sm font-semibold text-text-primary">Website URL</span>
            <span class="mt-0.5 block text-[11px] text-text-muted">Import from a page</span>
          </button>
          <button type="button" data-kb-type="document" class="kb-type-btn rounded-xl border border-border bg-elevated px-3 py-3 text-left transition-colors hover:border-green-500">
            <span class="block text-sm font-semibold text-text-primary">Document</span>
            <span class="mt-0.5 block text-[11px] text-text-muted">PDF, Word, TXT, MD</span>
          </button>
        </div>
        <select id="kb-source-type" class="sr-only" aria-hidden="true" tabindex="-1">
          <option value="text">Manual text</option>
          <option value="url">Website URL</option>
          <option value="document">Document upload</option>
        </select>
      </div>

      <div id="kb-field-text" class="flex flex-col gap-2">
        <label for="kb-manual-text" class="text-sm font-semibold text-text-primary">Content</label>
        <textarea
          id="kb-manual-text"
          rows="6"
          class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm leading-[1.5] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          placeholder="Paste business knowledge…"
        ></textarea>
      </div>

      <div id="kb-field-url" class="hidden flex-col gap-2">
        <label for="kb-url" class="text-sm font-semibold text-text-primary">Website URL</label>
        <input
          id="kb-url"
          type="url"
          class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
          placeholder="https://example.com/help"
        >
      </div>

      <div id="kb-field-file" class="hidden flex-col gap-2">
        <label class="text-sm font-semibold text-text-primary">Document file</label>
        <div class="overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
          <div
            id="kb-file-dropzone"
            class="flex cursor-pointer flex-col items-center justify-center rounded-[6px] border border-dashed border-border bg-elevated px-6 py-8 text-center transition-colors duration-150"
            data-dropzone
          >
            <div class="flex w-full max-w-[280px] flex-col items-center gap-2" data-dropzone-content>
              <img src="{{ asset('images/inbox/modals/upload-file.svg') }}" alt="" class="size-6" width="24" height="24">
              <div class="w-full text-center">
                <p class="text-xs font-medium text-text-body">
                  Drag &amp; drop or <span class="font-bold text-green-500">choose</span> a file
                </p>
                <p class="mt-1 text-[10px] font-medium text-text-body opacity-50">
                  PDF, DOC, DOCX, TXT, MD · Max recommended 10 MB
                </p>
              </div>
            </div>
            <div class="hidden w-full max-w-[320px] flex-col items-center gap-2" data-dropzone-preview>
              <img src="{{ asset('images/openai-key/upload.svg') }}" alt="" class="size-8" width="32" height="32">
              <p class="max-w-full truncate text-xs font-semibold text-green-500" data-filename></p>
              <p class="text-[10px] text-text-body/60" data-filesize></p>
              <button type="button" class="mt-1 text-[10px] text-red-500 hover:underline" data-dropzone-clear>Remove</button>
            </div>
            <input
              id="kb-file"
              type="file"
              accept=".txt,.pdf,.doc,.docx,.md,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/markdown"
              class="sr-only"
              data-dropzone-input
            >
          </div>
        </div>
        <p class="text-[11px] leading-[1.4] text-text-muted">
          Tip: Clear, well-structured documents (FAQs, product guides) work best for accurate answers.
        </p>
      </div>

      <p id="kb-add-error" class="hidden rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600"></p>

      <div class="flex items-center justify-between gap-3">
        @if (! empty($closeHref))
          <a href="{{ $closeHref }}" class="rounded-lg border border-green-500 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">Cancel</a>
        @else
          <span></span>
        @endif
        <button type="button" id="kb-prepare-btn" class="rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-opacity hover:opacity-90">
          Continue
        </button>
      </div>
    </div>

    <div id="kb-add-step-review" class="hidden flex-col gap-4">
      <div class="flex flex-col gap-1">
        <label for="kb-review-text" class="text-sm font-semibold text-text-primary">Review &amp; confirm</label>
        <p class="text-[11px] text-text-muted">Edit the extracted text if needed, then index it into the Knowledge Base.</p>
      </div>
      <textarea
        id="kb-review-text"
        rows="10"
        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm leading-[1.5] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
      ></textarea>
      <input type="hidden" id="kb-review-source" value="">
      <input type="hidden" id="kb-review-type" value="text">
      <div class="flex justify-between gap-3">
        <button type="button" id="kb-back-btn" class="rounded-lg border border-border px-4 py-3 text-sm font-semibold text-text-body transition-colors hover:bg-surface">Back</button>
        <button type="button" id="kb-confirm-btn" class="rounded-lg bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-opacity hover:opacity-90">
          Index to Knowledge Base
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('modal-add-sources');
  if (!modal) return;

  const typeEl = document.getElementById('kb-source-type');
  const typeButtons = modal.querySelectorAll('[data-kb-type]');
  const fieldText = document.getElementById('kb-field-text');
  const fieldUrl = document.getElementById('kb-field-url');
  const fieldFile = document.getElementById('kb-field-file');
  const stepInput = document.getElementById('kb-add-step-input');
  const stepReview = document.getElementById('kb-add-step-review');
  const errorEl = document.getElementById('kb-add-error');
  const reviewText = document.getElementById('kb-review-text');
  const reviewSource = document.getElementById('kb-review-source');
  const reviewType = document.getElementById('kb-review-type');
  const dropzone = document.getElementById('kb-file-dropzone');
  const fileInput = document.getElementById('kb-file');

  function showError(msg) {
    errorEl.textContent = msg || '';
    errorEl.classList.toggle('hidden', !msg);
  }

  function setType(value) {
    typeEl.value = value;
    typeButtons.forEach(function (btn) {
      const active = btn.getAttribute('data-kb-type') === value;
      btn.classList.toggle('border-green-500', active);
      btn.classList.toggle('bg-green-50', active);
      btn.classList.toggle('border-border', !active);
      btn.classList.toggle('bg-elevated', !active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    toggleFields();
  }

  function toggleFields() {
    const v = typeEl.value;
    fieldText.classList.toggle('hidden', v !== 'text');
    fieldText.classList.toggle('flex', v === 'text');
    fieldUrl.classList.toggle('hidden', v !== 'url');
    fieldUrl.classList.toggle('flex', v === 'url');
    fieldFile.classList.toggle('hidden', v !== 'document');
    fieldFile.classList.toggle('flex', v === 'document');
  }

  typeButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setType(btn.getAttribute('data-kb-type'));
      showError('');
    });
  });
  setType(typeEl.value || 'text');

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function setFilePreview(file) {
    if (!dropzone) return;
    const content = dropzone.querySelector('[data-dropzone-content]');
    const preview = dropzone.querySelector('[data-dropzone-preview]');
    const nameEl = dropzone.querySelector('[data-filename]');
    const sizeEl = dropzone.querySelector('[data-filesize]');

    if (!file) {
      if (content) content.classList.remove('hidden');
      if (preview) {
        preview.classList.add('hidden');
        preview.classList.remove('flex');
      }
      dropzone.classList.remove('border-green-500', 'bg-green-50');
      return;
    }

    if (content) content.classList.add('hidden');
    if (preview) {
      preview.classList.remove('hidden');
      preview.classList.add('flex');
    }
    if (nameEl) nameEl.textContent = file.name;
    if (sizeEl) sizeEl.textContent = formatBytes(file.size);
    dropzone.classList.add('border-green-500', 'bg-green-50');
  }

  function clearFile() {
    if (fileInput) fileInput.value = '';
    setFilePreview(null);
  }

  if (dropzone && fileInput) {
    dropzone.addEventListener('click', function (e) {
      if (e.target.closest('[data-dropzone-clear]')) return;
      fileInput.click();
    });

    fileInput.addEventListener('change', function () {
      const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      setFilePreview(file);
      showError('');
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('border-green-500', 'bg-green-50');
      });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (evt === 'dragleave' && !(fileInput.files && fileInput.files[0])) {
          dropzone.classList.remove('border-green-500', 'bg-green-50');
        }
      });
    });

    dropzone.addEventListener('drop', function (e) {
      const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]
        ? e.dataTransfer.files[0]
        : null;
      if (!file) return;
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      setFilePreview(file);
      showError('');
    });

    const clearBtn = dropzone.querySelector('[data-dropzone-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearFile();
      });
    }
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': modal.dataset.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || data.message || 'Request failed');
    return data;
  }

  document.getElementById('kb-prepare-btn').addEventListener('click', async function () {
    showError('');
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Working…';
    try {
      const botId = modal.dataset.botId;
      let text = '';
      let source = 'manual';
      let fileType = 'text';

      if (typeEl.value === 'text') {
        text = document.getElementById('kb-manual-text').value.trim();
        if (!text) throw new Error('Please enter content.');
        source = 'manual_entry';
      } else if (typeEl.value === 'url') {
        const url = document.getElementById('kb-url').value.trim();
        if (!url) throw new Error('Please enter a URL.');
        const scraped = await postJson(modal.dataset.scrapeUrl, { url: url });
        const pages = scraped.data || [];
        text = pages.map(function (p) { return p.text || ''; }).filter(Boolean).join('\n\n');
        if (!text) throw new Error('No text scraped from that URL.');
        source = url;
        fileType = 'url';
      } else {
        if (!fileInput.files || !fileInput.files[0]) throw new Error('Please choose a document file.');
        const fd = new FormData();
        fd.append('file', fileInput.files[0]);
        const res = await fetch(modal.dataset.extractUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': modal.dataset.csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: fd,
        });
        const extracted = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(extracted.error || 'Could not read that file. Try PDF, DOC, DOCX, TXT, or MD.');
        text = extracted.full_text || (extracted.text_chunks || []).join('\n\n');
        if (!text) throw new Error('No text could be extracted from this file.');
        source = extracted.filename || fileInput.files[0].name;
        fileType = 'document';
      }

      try {
        await postJson(modal.dataset.structureUrl, { text: text.slice(0, 12000), bot_id: botId });
      } catch (_) {}

      reviewText.value = text;
      reviewSource.value = source;
      reviewType.value = fileType;
      stepInput.classList.add('hidden');
      stepReview.classList.remove('hidden');
      stepReview.classList.add('flex');
    } catch (e) {
      showError(e.message || 'Failed');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Continue';
    }
  });

  document.getElementById('kb-back-btn').addEventListener('click', function () {
    stepReview.classList.add('hidden');
    stepReview.classList.remove('flex');
    stepInput.classList.remove('hidden');
  });

  document.getElementById('kb-confirm-btn').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Indexing…';
    try {
      const text = reviewText.value.trim();
      if (!text) throw new Error('Content is empty.');
      await postJson(modal.dataset.addUrl, {
        bot_id: modal.dataset.botId,
        documents: [text],
        metadata: [{
          source: reviewSource.value || 'manual',
          type: 'verified_info',
          file_type: reviewType.value || 'text',
          topic: 'business_information',
          verified_at: new Date().toISOString(),
        }],
      });
      window.location.href = @json($closeHref ?: route('openai-key.index', ['tab' => 'knowledge-base']));
    } catch (e) {
      alert(e.message || 'Failed to index');
      btn.disabled = false;
      btn.textContent = 'Index to Knowledge Base';
    }
  });
})();
</script>
