<x-layouts.app title="Chatbot - WapApp" active="automation.chatbot">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Chatbot</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Note:&nbsp;The parent WhatsApp template, which is the first template of each chatbot in the list, should be unique to ensure the correct flow. Once the flow has been saved, the first template cannot be edited or changed. A new flow must be created instead.
        </p>
      </div>

      @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          {{ $errors->first() }}
        </div>
      @endif

      <x-ui.listing-toolbar
        :action="route('chatbot.index')"
        :search-value="request('search')"
        :current-sort="$currentSort ?? request('sort', 'created_at')"
        :current-direction="$currentDirection ?? request('direction', 'desc')"
        :sort-options="[
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'updated_at', 'label' => 'Recently updated', 'direction' => 'desc'],
        ]"
      >
        <x-slot:actions>
          <a
            href="{{ route('chatbot.index') }}"
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
            Create
          </button>
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl  bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[800px] text-left">
            <thead>
              <tr class="">
                <th class="w-[54px] p-2 text-[13px]  leading-[1.5] whitespace-nowrap">SI. No</th>
                <th class="w-[320px] p-2 text-[13px]  leading-[1.5] ">Chatbot Name</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Published</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Actions</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">En/Disable</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($chatbots as $chatbot)
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $chatbot['serial'] }}</td>
                  <td class="w-[320px] p-2">
                    <p class="text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $chatbot['name'] }}</p>
                    <p class="text-xs font-normal leading-[1.5] text-text-body">Nodes: {{ $chatbot['node_count'] }}</p>
                  </td>
                  <td class="p-2">
                    @if ($chatbot['status'] === 'active')
                      <span data-chatbot-status class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-[green]">
                        Active
                      </span>
                    @else
                      <span data-chatbot-status class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted">
                        {{ $chatbot['status_label'] }}
                      </span>
                    @endif
                  </td>
                  <td class="p-2">
                    <div class="flex items-center gap-6">
                      <a href="{{ $chatbot['edit_url'] }}" class="flex size-5 items-center justify-center" aria-label="Edit" title="Edit">
                        <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <x-automation.listing-delete-button
                        :action="$chatbot['delete_url']"
                        confirm="Delete this chatbot flow? This action cannot be undone."
                        title="Delete chatbot"
                      />
                    </div>
                  </td>
                  <td class="p-2">
                    <form action="{{ $chatbot['toggle_url'] }}" method="POST" data-chatbot-toggle class="inline">
                      @csrf
                      @method('PATCH')
                      <x-ui.toggle-switch :active="$chatbot['is_active']" :submit="true" aria-label="En/Disable" />
                    </form>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="5" class="p-8 text-center text-sm text-text-muted">
                    No chatbot flows found. Click <strong>Create</strong> to build one.
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

  {{-- Create Chatbot Modal --}}
  <div id="modal-create-chatbot" class="fixed inset-0 z-50 hidden items-center justify-center bg-overlay" data-modal>
    <div class="w-full max-w-md rounded-xl bg-elevated p-6 shadow-xl">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-text-primary">Create Chatbot Flow</h3>
        <button type="button" id="close-create-modal" class="text-text-muted hover:text-text-body text-xl">&times;</button>
      </div>
      <form id="create-chatbot-form" class="mt-4 flex flex-col gap-4">
        <div id="create-form-errors" class="hidden rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>
        <div class="flex flex-col gap-1.5">
          <label for="create-chatbot-name" class="text-sm font-semibold text-text-body">
            Chatbot Name <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="create-chatbot-name"
            name="name"
            required
            placeholder="e.g. Customer Support Bot"
            class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
          >
        </div>
        @if ($showWhatsappLinePicker ?? false)
          <div class="flex flex-col gap-1.5">
            <label for="create-whatsapp-line-id" class="text-sm font-semibold text-text-body">WhatsApp Line (optional)</label>
            <select id="create-whatsapp-line-id" name="whatsapp_line_id" class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none">
              <option value="">-- None --</option>
              @foreach ($whatsappLines as $line)
                <option value="{{ $line->uuid }}">{{ $line->display_name ?: $line->phone }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="flex justify-end gap-3">
          <button type="button" id="cancel-create-modal" class="rounded-lg border border-divider bg-surface px-4 py-2 text-sm font-semibold text-text-body">Cancel</button>
          <button type="submit" id="create-submit-btn" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Create &amp; Open Builder</button>
        </div>
      </form>
    </div>
  </div>

  @push('scripts')
  <script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Create Modal ──
    var modal = document.getElementById('modal-create-chatbot');
    var openBtn = document.getElementById('open-create-modal-btn');
    var closeBtn = document.getElementById('close-create-modal');
    var cancelBtn = document.getElementById('cancel-create-modal');
    var form = document.getElementById('create-chatbot-form');
    var nameInput = document.getElementById('create-chatbot-name');
    var errorsEl = document.getElementById('create-form-errors');
    var submitBtn = document.getElementById('create-submit-btn');

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

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errorsEl.classList.add('hidden');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            var lineSelect = document.getElementById('create-whatsapp-line-id');
            var payload = { name: nameInput.value.trim() };
            if (lineSelect && lineSelect.value) {
                payload.whatsapp_line_id = lineSelect.value;
            }

            fetch("{{ route('chatbot.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
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
                    window.location.href = data.result.edit_url || ('/automation/chatbot/' + data.result.flow_id + '/edit');
                }
            }).catch(function (err) {
                errorsEl.textContent = err.message || 'An error occurred';
                errorsEl.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create & Open Builder';
            });
        });
    }
});
</script>
  @endpush
</x-layouts.app>
