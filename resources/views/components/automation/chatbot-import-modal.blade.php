{{-- Chatbot builder: Import Flow popup (drag & drop + guidelines + example files) --}}
<div
  id="modal-import-chatbot-flow"
  data-modal="import-chatbot-flow"
  class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/60 p-4"
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-import-chatbot-flow"
>
  <div class="flex max-h-[90vh] w-full max-w-[681px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-import-chatbot-flow" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Import chatbot flow
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          Upload a JSON export from this builder. Nodes will replace the current canvas — then click Save Flow.
        </p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
      <div class="flex flex-col gap-2">
        <label class="text-sm font-semibold leading-[1.4] text-text-primary">
          Choose a file to upload
        </label>

        <div
          id="chatbot-import-dropzone"
          class="flex cursor-pointer flex-col items-center justify-center rounded-[6px] border border-dashed border-border bg-elevated px-[68px] py-6 text-center transition-colors duration-150"
          data-dropzone
        >
          <div class="flex w-[240px] flex-col items-center gap-2" data-dropzone-content>
            <img src="{{ asset('images/inbox/modals/upload-file.svg') }}" alt="" class="size-6" width="24" height="24">
            <div class="w-full text-center">
              <p class="text-xs font-medium text-text-body">
                Drag &amp; Drop or <span class="font-bold text-green-500">choose</span> file to upload
              </p>
              <p class="mt-1 text-[10px] font-medium text-text-body opacity-50">Only JSON files (.json)</p>
            </div>
          </div>
          <div class="hidden flex-col items-center gap-2" data-dropzone-preview>
            <img src="{{ asset('images/automation/document-text.svg') }}" alt="" class="size-8" width="32" height="32">
            <p class="text-xs font-semibold text-green-500" data-filename></p>
            <p class="text-[10px] text-text-body/60" data-filesize></p>
            <button type="button" class="mt-1 text-[10px] text-red-500 hover:underline" data-dropzone-clear>Remove</button>
          </div>
          <input
            id="chatbot-import-modal-file"
            type="file"
            accept=".json,application/json"
            class="sr-only"
            data-dropzone-input
          >
        </div>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <p class="text-xs text-text-muted" data-import-status></p>
      <div class="flex gap-3">
        <button type="button" data-modal-close class="rounded-lg border border-divider bg-surface px-4 py-2 text-sm font-semibold text-text-body">
          Cancel
        </button>
        <button
          type="button"
          id="chatbot-import-confirm-btn"
          class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
          disabled
        >
          Import to canvas
        </button>
      </div>
    </div>

    <div class="space-y-3 rounded-[12px] bg-stat-blue/15 p-[14px] text-sm leading-[1.5] text-text-body">
      <div>
        <h6 class="text-sm font-bold text-text-body">File upload guidelines</h6>
        <ul class="mt-[10px] list-disc space-y-2 pl-5 font-normal text-text-muted">
          <li>
            <strong>Format:</strong> JSON only. Prefer a file exported from this builder
            (<code class="rounded bg-elevated px-1 text-xs">Export</code>), which looks like
            <code class="rounded bg-elevated px-1 text-xs">{ "nodes": [...], "edges": [...] }</code>.
          </li>
          <li>
            <strong>Must include:</strong> at least one node. Empty or wrong format will not load on the canvas.
          </li>
          <li>
            <strong>After import:</strong> click <strong>Save Flow</strong> so WhatsApp starts using the new graph.
            Import alone only updates the open canvas.
          </li>
          <li>
            <strong>Triggers:</strong> welcome nodes need keywords (e.g. <code class="rounded bg-elevated px-1 text-xs">hi, hello, menu</code>).
          </li>
        </ul>
      </div>

      <div class="border-t border-border/40 pt-3">
        <h6 class="text-sm font-bold text-text-body">Example files</h6>
        <p class="mt-1 text-xs text-text-muted">
          Download an example, import it here, then edit text / connect your AI bot. This is the shape we expect.
        </p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
          <a
            href="{{ asset('examples/chatbot/example-welcome-menu.json') }}"
            download="example-welcome-menu.json"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-green-500 bg-green-50 px-3 py-2 text-xs font-semibold text-green-600 hover:bg-green-50"
          >
            Download simple example
          </a>
          <a
            href="{{ asset('examples/chatbot/wapapp-help-bot.json') }}"
            download="wapapp-help-bot.json"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-divider bg-surface px-3 py-2 text-xs font-semibold text-text-body hover:bg-muted-surface"
          >
            Download full help bot example
          </a>
        </div>
        <p class="mt-2 text-[11px] text-text-muted">
          Full example = ask for the issue → short AI reply → agent handoff. Menu only under More options.
        </p>
      </div>
    </div>
  </div>
</div>
