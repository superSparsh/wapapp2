@php
  $exampleResponse = <<<'TXT'
{
  "success": true,
  "message": "Webhook received successfully"
}
TXT;

  $guideList = [
    'Your webhook endpoint must be configured to:',
    [
      'Accept POST requests with JSON payload',
      'Verify the request signature using the provided secret key',
      'Return a 200 OK response within 10 seconds',
    ],
  ];
@endphp

<x-layouts.app title="Webhook List - WapApp" active="webhooks.index">
  <div class="flex flex-col bg-surface">
    {{-- Flash message --}}
    @if (session('status'))
      <div class="mx-4 mt-3 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-1 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Webhook List</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Manage your webhook subscriptions and monitor delivery status
        </p>
      </div>
      <a
        href="{{ route('webhooks.logs') }}"
        class="fd-btn inline-flex shrink-0 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
      >
        View Delivery Logs
      </a>
    </div>

    <section class="flex flex-col gap-4 bg-surface p-4 pt-0">
      {{-- Add New Webhook Form --}}
      <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Add New Webhook</h2>

        <div class="flex gap-3 rounded-xl bg-stat-blue/15 p-3.5">
          <img src="{{ asset('images/webhooks/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
          <div class="flex min-w-0 flex-1 flex-col gap-2.5">
            <p class="text-sm font-bold leading-[1.4] text-text-body">Important: Webhook Implementation Guide</p>
            <div class="text-sm font-normal leading-[1.4] text-text-muted">
              <ul class="list-disc pl-[21px]">
                <li>{{ $guideList[0] }}</li>
              </ul>
              <ol class="list-[lower-alpha] pl-[42px]">
                @foreach ($guideList[1] as $item)
                  <li>{{ $item }}</li>
                @endforeach
              </ol>
            </div>
            <div class="flex flex-col gap-2">
              <p class="text-sm font-semibold leading-[1.4] text-text-primary">Example Response Format:</p>
              <div class="rounded-xl border border-solid border-border bg-elevated p-3.5">
                <pre class="whitespace-pre-wrap text-sm font-medium leading-[1.4] text-text-muted">{{ $exampleResponse }}</pre>
              </div>
            </div>
          </div>
        </div>

        <form action="{{ route('webhooks.store') }}" method="POST" id="webhook-form" data-validate-form>
          @csrf
          <div class="overflow-hidden rounded-xl border border-solid border-border-light bg-muted-surface p-4">
            <div class="flex flex-col gap-8">
              {{-- URL --}}
              <div class="flex flex-col gap-2">
                <label for="url" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Webhook URL&nbsp;<span class="text-[red]">*</span>
                </label>
                <div class="flex flex-col gap-2 rounded-xl border border-solid border-border bg-elevated py-1.5 pr-1.5 pl-3.5 sm:flex-row sm:items-center sm:gap-3">
                  <input
                    id="url"
                    name="url"
                    type="url"
                    required
                    placeholder="https://your-domain.com/webhook-endpoint"
                    value="{{ old('url') }}"
                    class="min-w-0 flex-1 bg-transparent py-2 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body/40 focus:outline-none"
                  >
                  <button
                    type="button"
                    id="test-url-btn"
                    class="fd-btn inline-flex shrink-0 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
                  >
                    Test Webhook
                  </button>
                </div>
                @error('url') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                <p class="text-sm font-medium leading-[1.4] text-text-muted">
                  The URL where we will send new lead notifications
                </p>
              </div>

              {{-- Description --}}
              <div class="flex flex-col gap-2">
                <label for="description" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Description&nbsp;<span class="text-[red]">*</span>
                </label>
                <input
                  id="description"
                  name="description"
                  type="text"
                  required
                  placeholder="e.g., Main CRM Integration"
                  value="{{ old('description') }}"
                  class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body/40 focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                @error('description') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                <p class="text-sm font-medium leading-[1.4] text-text-muted">
                  A label to identify this webhook's purpose
                </p>
              </div>

              {{-- Audience List --}}
              <div class="flex flex-col gap-2">
                <label for="audience_list_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Audience List (optional)
                </label>
                <select
                  id="audience_list_id"
                  name="audience_list_id"
                  class="w-full appearance-none rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
                  <option value="">Select an Audience List</option>
                  @foreach ($mailLists as $list)
                    <option value="{{ $list->uuid }}" @selected(old('audience_list_id') == $list->uuid)>{{ $list->name }}</option>
                  @endforeach
                </select>
                <p class="text-sm font-medium leading-[1.4] text-text-muted">
                  If selected, contacts from this webhook will be added to the chosen audience list.
                </p>
              </div>

              {{-- Event Type --}}
              <div class="flex flex-col gap-2">
                <p class="text-sm font-semibold leading-[1.4] text-text-primary">Event Type</p>
                <input type="hidden" name="events[]" value="new_lead">
                <label class="inline-flex items-center gap-2">
                  <img src="{{ asset('images/webhooks/radio-checked.svg') }}" alt="" class="size-4" width="16" height="16">
                  <span class="text-sm font-medium leading-[1.4] whitespace-nowrap text-green-500">&nbsp;New Lead Created</span>
                </label>
                <p class="text-sm font-medium leading-[1.4] text-text-muted">
                  This webhook will be triggered when a new lead is created
                </p>
              </div>

              {{-- Status --}}
              <div class="flex items-center gap-4">
                <span class="text-sm font-semibold leading-[1.4] whitespace-nowrap text-text-primary">Active</span>
                <input type="hidden" name="status" id="status-input" value="{{ old('status', 'active') }}">
                <button
                  type="button"
                  role="switch"
                  id="status-toggle"
                  aria-checked="{{ old('status', 'active') === 'active' ? 'true' : 'false' }}"
                  class="relative inline-flex h-[18px] w-10 shrink-0 rounded-full shadow-[inset_0px_6px_8px_3px_rgba(0,0,0,0.1)] transition-colors cursor-pointer {{ old('status', 'active') === 'active' ? 'bg-green-500' : 'bg-green-50' }}"
                >
                  <span class="pointer-events-none absolute top-[2px] size-[14px] rounded-full bg-gradient-to-b from-white to-[#e8eaea] shadow-[2px_1px_3px_rgba(0,0,0,0.25)] transition-[left] {{ old('status', 'active') === 'active' ? 'left-[24px]' : 'left-[2px]' }}"></span>
                </button>
              </div>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-end">
            <button
              type="submit"
              class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
            >
              Save Webhook
            </button>
          </div>
        </form>
      </div>

      {{-- Your Webhooks Table --}}
      <div class="flex flex-col gap-4 rounded-xl bg-elevated p-5">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">Your Webhooks</h2>

        @if ($subscriptions->isEmpty())
          <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <p class="text-base font-medium text-text-muted">No webhooks created yet</p>
            <p class="text-sm text-text-subtle opacity-60">Create your first webhook above to start receiving notifications.</p>
          </div>
        @else
          <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="overflow-x-auto">
              <table class="w-full min-w-[1100px] text-left">
                <thead>
                  <tr class="bg-elevated">
                    <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Description</th>
                    <th class="w-[320px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">URL</th>
                    <th class="w-[240px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Secret Key</th>
                    <th class="w-[80px] p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                    <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Event</th>
                    <th class="w-[110px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Last Triggered</th>
                    <th class="p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($subscriptions as $sub)
                    <tr class="border-t border-divider bg-elevated" data-sub-id="{{ $sub->id }}">
                      <td class="w-[160px] p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">
                        {{ $sub->description }}
                      </td>
                      <td class="w-[320px] max-w-[320px] truncate p-2 text-[13px] font-normal leading-[1.5] text-text-body">
                        {{ $sub->url }}
                      </td>
                      <td class="w-[240px] p-2">
                        <div class="flex flex-col gap-1">
                          <div class="flex items-center gap-1">
                            <div class="flex min-w-0 flex-1 items-center justify-center overflow-hidden rounded border border-solid border-border p-1">
                              <span class="secret-display min-w-0 flex-1 truncate text-xs font-medium leading-[1.4] text-[#939393]">
                                {{ str_repeat('•', 12) . substr($sub->secret_key, -4) }}
                              </span>
                            </div>
                            <button
                              type="button"
                              aria-label="Regenerate secret key"
                              class="regen-secret-btn flex shrink-0 items-center justify-center rounded bg-[rgba(0,128,0,0.1)] p-1"
                              data-url="{{ route('webhooks.regenerate-secret', $sub->id) }}"
                            >
                              <img src="{{ asset('images/webhooks/refresh-circle.svg') }}" alt="" class="size-5" width="20" height="20">
                            </button>
                          </div>
                          <p class="truncate text-[10px] font-medium leading-[1.4] text-[#5d5d5d]">
                            Use this key to verify webhook requests
                          </p>
                        </div>
                      </td>
                      <td class="w-[80px] p-2">
                        <button
                          type="button"
                          class="toggle-status-btn inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap transition-colors cursor-pointer {{ $sub->status === \App\Enums\WebhookSubscriptionStatus::Active ? 'bg-[rgba(0,128,0,0.1)] text-[green]' : 'bg-[rgba(255,0,0,0.1)] text-[red]' }}"
                          data-url="{{ route('webhooks.toggle', $sub->id) }}"
                        >
                          {{ $sub->status === \App\Enums\WebhookSubscriptionStatus::Active ? 'Active' : 'Inactive' }}
                        </button>
                      </td>
                      <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">
                        @foreach ($sub->events as $event)
                          {{ $event === 'new_lead' ? 'New Lead Created' : $event }}
                        @endforeach
                      </td>
                      <td class="w-[110px] p-2 text-[13px] font-normal leading-[1.5] whitespace-nowrap text-text-body">
                        {{ $sub->last_triggered_at ? $sub->last_triggered_at->format('Y-m-d H:i') : 'Never' }}
                      </td>
                      <td class="p-2">
                        <form action="{{ route('webhooks.destroy', $sub->id) }}" method="POST" onsubmit="return confirm('Delete this webhook?')">
                          @csrf
                          @method('DELETE')
                          <button type="submit" aria-label="Delete webhook">
                            <img src="{{ asset('images/webhooks/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                          </button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @if ($subscriptions->hasPages())
              <x-ui.table-pagination :paginator="$subscriptions" />
            @endif
          </div>
        @endif
      </div>
    </section>
  </div>

  <script>
    // Status toggle
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.toggle-status-btn');
      if (!btn) return;
      fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var isActive = data.status === 'active';
          btn.textContent = data.label;
          btn.className = 'toggle-status-btn inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap transition-colors cursor-pointer ' +
            (isActive ? 'bg-[rgba(0,128,0,0.1)] text-[green]' : 'bg-[rgba(255,0,0,0.1)] text-[red]');
        });
    });

    // Regenerate secret
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.regen-secret-btn');
      if (!btn) return;
      if (!confirm('Generate a new secret key? The old key will stop working.')) return;
      fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var row = btn.closest('tr');
          var display = row.querySelector('.secret-display');
          var key = data.secret_key;
          display.textContent = '••••••••••••' + key.slice(-4);
        });
    });

    // Status toggle in form
    var toggle = document.getElementById('status-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var input = document.getElementById('status-input');
        var isActive = input.value === 'active';
        input.value = isActive ? 'inactive' : 'active';
        var knob = toggle.querySelector('span');
        if (isActive) {
          toggle.classList.remove('bg-green-500');
          toggle.classList.add('bg-green-50');
          knob.classList.remove('left-[24px]');
          knob.classList.add('left-[2px]');
          toggle.setAttribute('aria-checked', 'false');
        } else {
          toggle.classList.remove('bg-green-50');
          toggle.classList.add('bg-green-500');
          knob.classList.remove('left-[2px]');
          knob.classList.add('left-[24px]');
          toggle.setAttribute('aria-checked', 'true');
        }
      });
    }

    // Test webhook button
    var testBtn = document.getElementById('test-url-btn');
    if (testBtn) {
      testBtn.addEventListener('click', function () {
        var urlInput = document.getElementById('url');
        if (!urlInput || !urlInput.value) { alert('Please enter a URL first.'); return; }
        alert('Save the webhook first, then use the test feature from the table.');
      });
    }
  </script>
</x-layouts.app>
