<div
  id="ai-suggestions-modal"
  class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4"
  data-ai-suggestions-modal
  role="dialog"
  aria-modal="true"
  aria-labelledby="ai-suggestions-title"
>
  <div class="flex max-h-[90vh] w-full max-w-lg flex-col gap-4 overflow-hidden rounded-xl bg-elevated p-4 shadow-lg">
    <div class="flex items-center justify-between gap-2">
      <h2 id="ai-suggestions-title" class="text-lg font-semibold text-text-body">AI body suggestions</h2>
      <button type="button" class="text-text-muted hover:text-text-body" data-ai-suggestions-close aria-label="Close">&times;</button>
    </div>
    <p class="text-sm text-text-subtle">Describe what you need. Suggestions use $(variable) placeholders.</p>
    <textarea
      id="ai-suggestions-prompt"
      rows="3"
      class="fd-input w-full rounded-xl border border-border p-3"
      placeholder="e.g. Order confirmation for an e-commerce store"
    ></textarea>
    <button type="button" class="fd-btn-sm w-fit rounded bg-green-500 px-4 py-2 text-primary-2" data-ai-suggestions-generate>
      Generate suggestions
    </button>
    <div id="ai-suggestions-results" class="flex flex-col gap-2 overflow-y-auto"></div>
    <p id="ai-suggestions-error" class="hidden text-sm text-red-600"></p>
  </div>
</div>
