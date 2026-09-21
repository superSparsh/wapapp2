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
          Content is indexed into Chroma via the AI service — not stored in MySQL.
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
        <select id="kb-source-type" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm">
          <option value="text">Manual text</option>
          <option value="url">Website URL</option>
          <option value="document">Document upload</option>
        </select>
      </div>

      <div id="kb-field-text" class="flex flex-col gap-2">
        <label for="kb-manual-text" class="text-sm font-semibold text-text-primary">Content</label>
        <textarea id="kb-manual-text" rows="6" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm" placeholder="Paste business knowledge…"></textarea>
      </div>

      <div id="kb-field-url" class="hidden flex-col gap-2">
        <label for="kb-url" class="text-sm font-semibold text-text-primary">Website URL</label>
        <input id="kb-url" type="url" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm" placeholder="https://example.com">
      </div>

      <div id="kb-field-file" class="hidden flex-col gap-2">
        <label for="kb-file" class="text-sm font-semibold text-text-primary">Document</label>
        <input id="kb-file" type="file" accept=".txt,.pdf,.doc,.docx" class="w-full text-sm">
      </div>

      <p id="kb-add-error" class="hidden text-sm font-medium text-red-600"></p>

      <div class="flex justify-between">
        @if (! empty($closeHref))
          <a href="{{ $closeHref }}" class="rounded border border-green-500 px-4 py-3 text-sm font-semibold text-green-500">Cancel</a>
        @endif
        <button type="button" id="kb-prepare-btn" class="rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">
          Continue
        </button>
      </div>
    </div>

    <div id="kb-add-step-review" class="hidden flex-col gap-4">
      <label class="text-sm font-semibold text-text-primary">Review &amp; confirm</label>
      <textarea id="kb-review-text" rows="10" class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm"></textarea>
      <input type="hidden" id="kb-review-source" value="">
      <input type="hidden" id="kb-review-type" value="text">
      <div class="flex justify-between gap-3">
        <button type="button" id="kb-back-btn" class="rounded border border-border px-4 py-3 text-sm font-semibold">Back</button>
        <button type="button" id="kb-confirm-btn" class="rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2">
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
  const fieldText = document.getElementById('kb-field-text');
  const fieldUrl = document.getElementById('kb-field-url');
  const fieldFile = document.getElementById('kb-field-file');
  const stepInput = document.getElementById('kb-add-step-input');
  const stepReview = document.getElementById('kb-add-step-review');
  const errorEl = document.getElementById('kb-add-error');
  const reviewText = document.getElementById('kb-review-text');
  const reviewSource = document.getElementById('kb-review-source');
  const reviewType = document.getElementById('kb-review-type');

  function showError(msg) {
    errorEl.textContent = msg || '';
    errorEl.classList.toggle('hidden', !msg);
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
  typeEl.addEventListener('change', toggleFields);
  toggleFields();

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
        const fileInput = document.getElementById('kb-file');
        if (!fileInput.files || !fileInput.files[0]) throw new Error('Please choose a file.');
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
        if (!res.ok) throw new Error(extracted.error || 'Extract failed');
        text = extracted.full_text || (extracted.text_chunks || []).join('\n\n');
        if (!text) throw new Error('No text extracted from file.');
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
