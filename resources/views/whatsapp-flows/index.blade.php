<x-layouts.app title="WhatsApp Flows - WapApp" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">WhatsApp Flows</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Build interactive in-chat forms for surveys, lead capture, and bookings that users fill out directly within WhatsApp.
        </p>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
          {{ session('status') }}
        </div>
      @endif

      <x-ui.listing-toolbar
        :action="route('whatsapp-flows.index')"
        :search-value="request('search')"
        search-placeholder="Search flows..."
        :current-sort="$currentSort ?? request('sort', 'created_at')"
        :current-direction="$currentDirection ?? request('direction', 'desc')"
        :sort-options="[
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'published_at', 'label' => 'Recently published', 'direction' => 'desc'],
        ]"
      >
        <x-slot:filters>
          <x-ui.select
            name="status"
            variant="listing"
            data-listing-filter
            class="w-[148px] shrink-0"
            aria-label="Filter by status"
          >
            @foreach ($statusOptions as $value => $label)
              <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
          </x-ui.select>
        </x-slot:filters>
        <x-slot:actions>
          <a
            href="{{ route('whatsapp-flows.index') }}"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded-lg border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            <img src="{{ asset('images/automation/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </a>
          <button
            type="button"
            id="open-create-modal-btn"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-5" width="20" height="20">
            Create Flow
          </button>
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[800px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                <th class="w-[280px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Flow Name</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Submissions</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Published</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($flows as $flow)
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $flow['serial'] }}</td>
                  <td class="w-[280px] p-2">
                    <a href="{{ $flow['show_url'] }}" class="text-[13px] font-semibold leading-[1.5] text-text-subtle hover:text-green-500">{{ $flow['name'] }}</a>
                    <p class="text-xs font-normal leading-[1.5] text-text-body">Screens: {{ $flow['screen_count'] }}</p>
                  </td>
                  <td class="p-2">
                    <a href="{{ $flow['stats_url'] }}" class="text-[13px] font-medium leading-[1.5] text-text-body hover:text-green-500">{{ $flow['submission_count'] }}</a>
                  </td>
                  <td class="p-2">
                    @if ($flow['status'] === 'active')
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-[green]">
                        Active
                      </span>
                    @elseif ($flow['status'] === 'archived')
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(255,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-red-500">
                        Archived
                      </span>
                    @else
                      <span class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted">
                        {{ $flow['status_label'] }}
                      </span>
                    @endif
                  </td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">
                    {{ $flow['published_at'] ?? '—' }}
                  </td>
                  <td class="p-2">
                    <div class="flex items-center gap-4">
                      <a href="{{ $flow['show_url'] }}" class="flex size-5 items-center justify-center" aria-label="View" title="View">
                        <img src="{{ asset('images/automation/eye.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      @if ($flow['is_active'])
                        <button
                          type="button"
                          class="preview-flow-btn flex size-5 items-center justify-center"
                          data-preview-url="{{ $flow['preview_url'] }}"
                          aria-label="Preview"
                          title="Preview in WhatsApp"
                        >
                          <img src="{{ asset('images/automation/send-flow.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                        <form action="{{ $flow['archive_url'] }}" method="POST" class="inline" data-confirm="Deprecate / archive this published flow on WhatsApp?" data-confirm-variant="danger">
                          @csrf
                          @method('PATCH')
                          <button type="submit" class="flex size-5 items-center justify-center" aria-label="Archive" title="Archive / Deprecate">
                            <img src="{{ asset('images/automation/trash.svg') }}" alt="" class="size-5 opacity-70" width="20" height="20">
                          </button>
                        </form>
                      @endif
                      <a href="{{ $flow['edit_url'] }}" class="flex size-5 items-center justify-center" aria-label="Edit" title="Edit Builder">
                        <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <form action="{{ $flow['duplicate_url'] }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="flex size-5 items-center justify-center" aria-label="Duplicate" title="Duplicate">
                          <img src="{{ asset('images/automation/import-flow.svg') }}" alt="" class="size-5" width="20" height="20">
                        </button>
                      </form>
                      @unless ($flow['is_active'])
                        <x-automation.listing-delete-button
                          :action="$flow['delete_url']"
                          confirm="Delete this flow? This action cannot be undone."
                          title="Delete flow"
                        />
                      @endunless
                    </div>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-8 text-center text-sm text-text-muted">
                    No WhatsApp Flows found. Click <strong>Create Flow</strong> to build one.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($paginator->hasPages())
          <x-ui.table-pagination :paginator="$paginator" />
        @endif
      </div>
    </section>
  </div>

  {{-- Create Flow Modal --}}
  <div id="modal-create-flow" class="fixed inset-0 z-50 hidden items-center justify-center bg-overlay" data-modal>
    <div class="w-full max-w-md rounded-xl bg-elevated p-6 shadow-xl">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-text-primary">Create WhatsApp Flow</h3>
        <button type="button" id="close-create-modal" class="text-text-muted hover:text-text-body text-xl">&times;</button>
      </div>
      <form id="create-flow-form" class="mt-4 flex flex-col gap-4">
        <div id="create-form-errors" class="hidden rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>
        <div class="flex flex-col gap-1.5">
          <label for="create-flow-name" class="text-sm font-semibold text-text-body">
            Flow Name <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="create-flow-name"
            name="name"
            required
            placeholder="e.g. Customer Feedback Survey"
            class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
          >
        </div>
        <div class="flex flex-col gap-1.5">
          <span class="text-sm font-semibold text-text-body">Categories <span class="text-red-500">*</span></span>
          <p class="text-xs text-text-muted">Meta requires at least one category.</p>
          <div class="grid grid-cols-2 gap-2 rounded-lg border border-divider bg-surface p-3">
            @foreach (config('whatsapp-flows.categories', ['OTHER']) as $category)
              <label class="flex items-center gap-2 text-xs text-text-body">
                <input
                  type="checkbox"
                  name="categories[]"
                  value="{{ $category }}"
                  class="create-flow-category rounded border-divider"
                  @checked($category === 'OTHER')
                >
                {{ str_replace('_', ' ', $category) }}
              </label>
            @endforeach
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" id="cancel-create-modal" class="rounded-lg border border-divider bg-surface px-4 py-2 text-sm font-semibold text-text-body">Cancel</button>
          <button type="submit" id="create-submit-btn" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Create &amp; Open Builder</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Legacy: Meta/Facebook Flow Preview opens via clickable link (popup blockers block async window.open) --}}
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
      var modal = document.getElementById('modal-create-flow');
      var openBtn = document.getElementById('open-create-modal-btn');
      var closeBtn = document.getElementById('close-create-modal');
      var cancelBtn = document.getElementById('cancel-create-modal');
      var form = document.getElementById('create-flow-form');
      var nameInput = document.getElementById('create-flow-name');
      var errorsEl = document.getElementById('create-form-errors');
      var submitBtn = document.getElementById('create-submit-btn');
      var previewModal = document.getElementById('modal-flow-preview');
      var previewContent = document.getElementById('flow-preview-content');

      function openModal() {
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          nameInput.value = '';
          errorsEl.classList.add('hidden');
          setTimeout(function() { nameInput.focus(); }, 100);
      }

      function closeModal() {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
      }

      function openPreviewModal() {
          previewModal.classList.remove('hidden');
          previewModal.classList.add('flex');
      }

      function closePreviewModal() {
          previewModal.classList.add('hidden');
          previewModal.classList.remove('flex');
      }

      if (openBtn) openBtn.addEventListener('click', openModal);
      if (closeBtn) closeBtn.addEventListener('click', closeModal);
      if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
      document.getElementById('close-flow-preview-modal')?.addEventListener('click', closePreviewModal);
      document.getElementById('dismiss-flow-preview-modal')?.addEventListener('click', closePreviewModal);

      if (modal) {
          modal.addEventListener('click', function (e) {
              if (e.target === modal) closeModal();
          });
      }
      if (previewModal) {
          previewModal.addEventListener('click', function (e) {
              if (e.target === previewModal) closePreviewModal();
          });
      }

      if (form) {
          form.addEventListener('submit', function (e) {
              e.preventDefault();
              errorsEl.classList.add('hidden');
              submitBtn.disabled = true;
              submitBtn.textContent = 'Creating...';

              var csrf = document.querySelector('meta[name="csrf-token"]').content;
              var categories = Array.from(document.querySelectorAll('.create-flow-category:checked')).map(function (el) {
                  return el.value;
              });
              if (categories.length === 0) {
                  errorsEl.textContent = 'Select at least one category.';
                  errorsEl.classList.remove('hidden');
                  submitBtn.disabled = false;
                  submitBtn.textContent = 'Create & Open Builder';
                  return;
              }

              fetch("{{ route('whatsapp-flows.store') }}", {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': csrf,
                      'Accept': 'application/json',
                  },
                  body: JSON.stringify({
                      name: nameInput.value.trim(),
                      categories: categories,
                  }),
              }).then(function (resp) {
                  return resp.json().then(function (result) {
                      return { ok: resp.ok, result: result };
                  });
              }).then(function (data) {
                  if (!data.ok) {
                      if (data.result.errors) {
                          var msgs = [];
                          for (var key in data.result.errors) {
                              data.result.errors[key].forEach(function (m) { msgs.push(m); });
                          }
                          errorsEl.innerHTML = '<ul class="list-inside list-disc"><li>' + msgs.join('</li><li>') + '</li></ul>';
                      } else {
                          errorsEl.textContent = data.result.message || 'Something went wrong';
                      }
                      errorsEl.classList.remove('hidden');
                      submitBtn.disabled = false;
                      submitBtn.textContent = 'Create & Open Builder';
                      return;
                  }
                  if (data.result.success) {
                      window.location.href = '/whatsapp-flows/' + data.result.flow_id + '/edit';
                  }
              }).catch(function (err) {
                  errorsEl.textContent = err.message || 'An error occurred';
                  errorsEl.classList.remove('hidden');
                  submitBtn.disabled = false;
                  submitBtn.textContent = 'Create & Open Builder';
              });
          });
      }

      document.querySelectorAll('.preview-flow-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
              var previewEndpoint = btn.dataset.previewUrl;
              if (!previewEndpoint || !previewContent) return;

              previewContent.innerHTML = '<p class="text-text-muted">Loading preview…</p>';
              openPreviewModal();

              fetch(previewEndpoint, { headers: { 'Accept': 'application/json' } })
                  .then(function (resp) {
                      return resp.json().then(function (data) {
                          return { ok: resp.ok, data: data };
                      });
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
