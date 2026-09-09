<x-layouts.app title="{{ $flow->name }} - WhatsApp Flows" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <a href="{{ route('whatsapp-flows.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to list</a>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-4">
          <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }}</h1>
          <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('whatsapp-flows.edit', $flow) }}" class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50">
              <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-4" width="16" height="16">
              Edit Builder
            </a>
            @if (! $flow->isActive())
              <form action="{{ route('whatsapp-flows.publish', $flow) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-white transition-opacity hover:opacity-90">
                  <img src="{{ asset('images/automation/send-flow.svg') }}" alt="" class="size-4" width="16" height="16">
                  Publish
                </button>
              </form>
            @else
              <button
                type="button"
                class="preview-flow-btn fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500"
                data-preview-url="{{ route('whatsapp-flows.preview', $flow) }}"
              >
                Open Flow Preview
              </button>
              <form action="{{ route('whatsapp-flows.archive', $flow) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-red-400 bg-red-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-red-500 transition-colors hover:bg-red-100">Archive</button>
              </form>
            @endif
            <form action="{{ route('whatsapp-flows.duplicate', $flow) }}" method="POST" class="inline">
              @csrf
              <button type="submit" class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50">Duplicate</button>
            </form>
          </div>
        </div>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
      @endif

      {{-- Details Cards --}}
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Status</p>
          @if ($flow->isActive())
            <span class="mt-1 inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-xs font-medium text-[green]">Active</span>
          @elseif ($flow->status->value === 'archived')
            <span class="mt-1 inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-2 py-1 text-xs font-medium text-red-500">Archived</span>
          @else
            <span class="mt-1 inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-xs font-medium text-text-muted">{{ $flow->status->label() }}</span>
          @endif
        </div>
        <div class="rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Submissions</p>
          <p class="mt-1 text-xl font-bold text-text-primary">{{ $submissions->count() === 20 ? '20+' : $flow->submissions()->count() }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Published</p>
          <p class="mt-1 text-sm font-semibold text-text-body">{{ $flow->published_at?->format('M d, Y H:i') ?? 'Not published' }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Meta Flow ID</p>
          <p class="mt-1 truncate text-sm font-semibold text-text-body">{{ $flow->meta_flow_id ?? '—' }}</p>
        </div>
      </div>

      @if ($flow->data_exchange_endpoint)
        <div class="rounded-xl bg-elevated p-4 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Data Exchange Endpoint</p>
          <div class="mt-1 flex items-center gap-2">
            <code id="exchange-url" class="flex-1 truncate rounded bg-surface px-3 py-2 text-xs text-text-body">{{ $flow->data_exchange_endpoint }}</code>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('exchange-url').textContent)" class="rounded bg-green-50 px-3 py-2 text-xs font-semibold text-green-500 hover:bg-green-100">Copy</button>
          </div>
        </div>
      @endif

      {{-- Recent Submissions --}}
      <div class="rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between border-b border-divider p-4">
          <h2 class="text-base font-semibold text-text-primary">Recent Submissions</h2>
          <a href="{{ route('whatsapp-flows.stats', $flow) }}" class="text-sm font-medium text-green-500 hover:underline">View All Stats</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[600px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="p-3 text-[13px] font-medium text-text-body">Phone</th>
                <th class="p-3 text-[13px] font-medium text-text-body">Status</th>
                <th class="p-3 text-[13px] font-medium text-text-body">Form Data</th>
                <th class="p-3 text-[13px] font-medium text-text-body">Received</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($submissions as $sub)
                <tr class="border-t border-divider">
                  <td class="p-3 text-[13px] text-text-body">{{ $sub->contact_phone }}</td>
                  <td class="p-3">
                    @if ($sub->status === 'processed')
                      <span class="rounded bg-[rgba(0,128,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-[green]">Processed</span>
                    @elseif ($sub->status === 'failed')
                      <span class="rounded bg-[rgba(255,0,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-red-500">Failed</span>
                    @else
                      <span class="rounded bg-[rgba(0,0,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-text-muted">Received</span>
                    @endif
                  </td>
                  <td class="p-3 text-[12px] text-text-subtle">
                    <span class="block max-w-[300px] truncate">{{ json_encode($sub->form_data) }}</span>
                  </td>
                  <td class="p-3 text-[13px] text-text-body">{{ $sub->created_at->format('M d, H:i') }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider">
                  <td colspan="4" class="p-8 text-center text-sm text-text-muted">No submissions yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div id="modal-flow-preview" class="fixed inset-0 z-50 hidden items-center justify-center bg-overlay">
    <div class="w-full max-w-md rounded-xl bg-elevated p-6 shadow-xl">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-text-primary">Flow Preview</h3>
        <button type="button" id="close-flow-preview-modal" class="text-xl text-text-muted hover:text-text-body">&times;</button>
      </div>
      <div id="flow-preview-content" class="mt-4 text-sm text-text-body">
        <p class="text-text-muted">Loading preview…</p>
      </div>
      <div class="mt-6 flex justify-end">
        <button type="button" id="dismiss-flow-preview-modal" class="rounded-lg border border-divider bg-surface px-4 py-2 text-sm font-semibold text-text-body">Close</button>
      </div>
    </div>
  </div>

  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      var previewModal = document.getElementById('modal-flow-preview');
      var previewContent = document.getElementById('flow-preview-content');

      function openPreviewModal() {
          previewModal.classList.remove('hidden');
          previewModal.classList.add('flex');
      }
      function closePreviewModal() {
          previewModal.classList.add('hidden');
          previewModal.classList.remove('flex');
      }

      document.getElementById('close-flow-preview-modal')?.addEventListener('click', closePreviewModal);
      document.getElementById('dismiss-flow-preview-modal')?.addEventListener('click', closePreviewModal);
      previewModal?.addEventListener('click', function (e) {
          if (e.target === previewModal) closePreviewModal();
      });

      document.querySelectorAll('.preview-flow-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
              var previewEndpoint = btn.dataset.previewUrl;
              if (!previewEndpoint || !previewContent) return;
              previewContent.innerHTML = '<p class="text-text-muted">Loading preview…</p>';
              openPreviewModal();
              fetch(previewEndpoint, { headers: { 'Accept': 'application/json' } })
                  .then(function (resp) {
                      return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
                  })
                  .then(function (result) {
                      if (result.ok && result.data.success && result.data.preview_url) {
                          var href = result.data.preview_url;
                          previewContent.innerHTML =
                              '<p class="mb-4">Click below to open the Meta / Facebook Flow preview:</p>' +
                              '<a href="' + href + '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Open Flow Preview</a>' +
                              '<p class="mt-3 break-all text-xs text-text-muted">' + href + '</p>';
                      } else {
                          previewContent.innerHTML = '<p class="text-red-500">' + (result.data.message || 'Failed to load preview.') + '</p>';
                      }
                  })
                  .catch(function () {
                      previewContent.innerHTML = '<p class="text-red-500">An error occurred while fetching the preview.</p>';
                  });
          });
      });
  });
  </script>
  @endpush
</x-layouts.app>
