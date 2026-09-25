@php
  $domainUrl = $integration->domainUrl();
  $enabledScopeKeys = collect($enabledScopes)->pluck('key')->all();
@endphp

<x-layouts.app title="Shopify Trigger Scopes - WapApp" active="integration.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-wrap items-center gap-3 p-4">
      <div class="min-w-0 flex-1">
        <h1 class="fd-page-title text-2xl">Add Trigger Scope</h1>
        <p class="fd-page-note mt-1">Map Shopify webhooks to WhatsApp templates so customers get notified automatically.</p>
      </div>
      <a href="{{ route('integration.index') }}"
        class="fd-btn inline-flex shrink-0 items-center justify-center rounded border border-border px-4 py-3 text-sm font-semibold text-text-primary hover:bg-elevated">
        Back to Dashboard
      </a>
    </div>

    @if (session('success'))
      <div class="mx-4 mb-2 rounded-lg bg-green-100 px-4 py-2 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="mx-4 mb-2 rounded-lg bg-red-100 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if (! $domainUrl)
      <div class="mx-4 mb-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
        <span>Shopify store domain is required before enabling webhook scopes.</span>
        <a href="{{ route('integration.index') }}" class="font-semibold underline">Set domain on Dashboard</a>
      </div>
    @else
      <div class="mx-4 mb-2 rounded-xl border border-border bg-elevated px-4 py-3 text-sm text-text-muted">
        Store domain: <span class="font-medium text-text-primary">{{ $domainUrl }}</span>
      </div>
    @endif

    <section class="grid items-start gap-4 p-4 pt-0 lg:grid-cols-2">
      {{-- Left: form --}}
      <div class="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-elevated p-5">
        <form id="shopify-scope-form" class="flex flex-col gap-4">
          @csrf
          <div class="flex flex-col gap-2">
            <label for="scope_key" class="text-sm font-semibold text-text-primary">
              Select a webhook scope <span class="text-red-500">*</span>
            </label>
            <select id="scope_key" name="scope_key" required
              class="w-full rounded-xl border border-border bg-surface p-3.5 text-sm font-medium text-text-primary focus:border-green-500 focus:outline-none">
              <option value="">Select a webhook scope</option>
              @foreach ($scopeCatalog as $key => $meta)
                <option value="{{ $key }}"
                  data-needs-mail-list="{{ ! empty($meta['needs_mail_list']) ? '1' : '0' }}"
                  @disabled(in_array($key, $enabledScopeKeys, true))>
                  {{ $meta['label'] ?? $key }}
                  @if (in_array($key, $enabledScopeKeys, true)) (enabled) @endif
                </option>
              @endforeach
            </select>
            <p id="scope-info" class="hidden text-xs text-text-muted"></p>
          </div>

          <div class="flex items-center justify-between gap-3 rounded-xl bg-muted-surface p-3.5">
            <div>
              <p class="text-sm font-semibold text-text-primary">Status</p>
              <p class="text-xs text-text-muted">Enable WhatsApp notifications for this scope</p>
            </div>
            <label class="inline-flex cursor-pointer items-center gap-2">
              <input type="checkbox" id="scope_enabled" name="enabled" value="1" checked class="size-4 rounded border-border text-green-500">
              <span class="text-sm font-medium text-text-primary">Enabled</span>
            </label>
          </div>

          <div class="flex flex-col gap-2">
            <label for="template_id" class="text-sm font-semibold text-text-primary">
              Select Template <span class="text-red-500">*</span>
            </label>
            <select id="template_id" name="template_id" required
              class="w-full rounded-xl border border-border bg-surface p-3.5 text-sm font-medium text-text-primary focus:border-green-500 focus:outline-none">
              <option value="">Select an approved template</option>
              @forelse ($templates as $tpl)
                <option value="{{ $tpl->id }}">{{ $tpl->name }}@if ($tpl->code) ({{ $tpl->code }})@endif</option>
              @empty
                <option value="" disabled>No approved templates found</option>
              @endforelse
            </select>
            <a href="{{ route('templates.index') }}" class="text-xs font-medium text-green-600 hover:underline">
              Create / manage templates →
            </a>
          </div>

          <div id="mail-list-wrap" class="hidden flex-col gap-2">
            <label for="mail_list_id" class="text-sm font-semibold text-text-primary">
              Audience (Mail List) <span class="text-red-500">*</span>
            </label>
            <select id="mail_list_id" name="mail_list_id"
              class="w-full rounded-xl border border-border bg-surface p-3.5 text-sm font-medium text-text-primary focus:border-green-500 focus:outline-none">
              <option value="">Select audience to notify</option>
              @foreach ($mailLists as $list)
                <option value="{{ $list->id }}">{{ $list->name }}</option>
              @endforeach
            </select>
            <p class="text-xs text-text-muted">Product scopes notify everyone on the selected audience list.</p>
          </div>

          <p id="scope-form-status" class="hidden text-sm font-medium" role="status"></p>

          <button type="submit" id="scope-submit-btn"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 hover:opacity-90 disabled:opacity-60">
            Create Webhook
          </button>
        </form>
      </div>

      {{-- Right: enabled scopes --}}
      <div class="flex min-w-0 flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-text-primary">Enabled Webhooks</h2>
          <div class="relative max-w-xs flex-1">
            <img src="{{ asset('images/integration/search.svg') }}" alt="" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2" width="16" height="16">
            <input type="search" id="scope-search" placeholder="Search scopes"
              class="w-full rounded-lg border border-border bg-elevated py-2.5 pl-9 pr-3 text-sm text-text-primary placeholder:text-text-muted focus:border-green-500 focus:outline-none">
          </div>
        </div>

        <div id="enabled-scopes-list" class="flex max-h-[640px] flex-col gap-3 overflow-y-auto rounded-xl border border-border bg-elevated p-3">
          @forelse ($enabledScopes as $scope)
            <div class="flex items-center gap-3 rounded-xl border border-border bg-surface p-3.5" data-scope-row data-scope-key="{{ $scope['key'] }}" data-scope-label="{{ strtolower($scope['label']) }}">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-text-primary">{{ $scope['label'] }}</p>
                <p class="truncate text-xs text-text-muted">
                  Template #{{ $scope['template_id'] ?? '—' }}
                  @if ($scope['needs_mail_list'])
                    · Audience #{{ $scope['mail_list_id'] ?? '—' }}
                  @endif
                </p>
              </div>
              <button type="button" data-remove-scope="{{ $scope['key'] }}"
                class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border hover:bg-red-50"
                aria-label="Remove {{ $scope['label'] }}">
                <img src="{{ asset('images/integration/trash.svg') }}" alt="" class="size-5" width="20" height="20">
              </button>
            </div>
          @empty
            <p class="p-6 text-center text-sm text-text-muted" data-empty-scopes>No webhook scopes configured yet.</p>
          @endforelse
        </div>
      </div>
    </section>
  </div>

  <script>
    (function () {
      const form = document.getElementById('shopify-scope-form');
      const scopeSelect = document.getElementById('scope_key');
      const mailWrap = document.getElementById('mail-list-wrap');
      const mailSelect = document.getElementById('mail_list_id');
      const statusEl = document.getElementById('scope-form-status');
      const submitBtn = document.getElementById('scope-submit-btn');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || form?.querySelector('[name="_token"]')?.value;

      const catalog = @json($scopeCatalogJson);

      const infoByKey = Object.fromEntries(catalog.map((row) => [row.key, row]));

      const toggleMailList = () => {
        const option = scopeSelect?.selectedOptions?.[0];
        const needs = option?.dataset?.needsMailList === '1';
        if (!mailWrap) return;
        mailWrap.classList.toggle('hidden', !needs);
        mailWrap.classList.toggle('flex', needs);
        if (mailSelect) mailSelect.required = needs;
      };

      scopeSelect?.addEventListener('change', toggleMailList);
      toggleMailList();

      const setStatus = (message, ok) => {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.className = 'text-sm font-medium ' + (ok ? 'text-green-600' : 'text-red-600');
        statusEl.classList.remove('hidden');
      };

      form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!scopeSelect?.value) {
          setStatus('Select a webhook scope.', false);
          return;
        }

        submitBtn.disabled = true;
        setStatus('Saving…', true);

        try {
          const payload = {
            scope_key: scopeSelect.value,
            enabled: document.getElementById('scope_enabled')?.checked ? 1 : 0,
            template_id: document.getElementById('template_id')?.value || null,
            mail_list_id: mailSelect?.value || null,
          };

          const res = await fetch(@json(route('integration.shopify.scopes.save')), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf,
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
          });
          const data = await res.json();
          if (!res.ok || !data.success) {
            setStatus(data.message || 'Failed to save webhook scope.', false);
            submitBtn.disabled = false;
            return;
          }

          window.showSuccessToast?.(data.message || 'Webhook scope saved.');
          setTimeout(() => location.reload(), 600);
        } catch (e) {
          setStatus('Request failed. Please try again.', false);
          submitBtn.disabled = false;
        }
      });

      document.querySelectorAll('[data-remove-scope]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const key = btn.getAttribute('data-remove-scope');
          if (!key) return;

          const label = btn.closest('[data-scope-row]')?.querySelector('p')?.textContent?.trim() || key;
          const confirmed = await (window.showAppConfirm?.({
            title: 'Delete webhook scope',
            message: `Delete “${label}”? WhatsApp notifications for this Shopify event will stop.`,
            variant: 'danger',
            confirmLabel: 'Delete',
          }) ?? Promise.resolve(window.confirm(`Delete “${label}”?`)));

          if (!confirmed) return;

          try {
            const res = await fetch(@json(route('integration.shopify.scopes.remove')), {
              method: 'DELETE',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
              },
              body: JSON.stringify({ scope_key: key }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
              window.showErrorToast?.(data.message || 'Failed to remove scope.');
              return;
            }
            window.showSuccessToast?.(data.message || 'Scope removed.');
            setTimeout(() => location.reload(), 500);
          } catch (e) {
            window.showErrorToast?.('Request failed.');
          }
        });
      });

      document.getElementById('scope-search')?.addEventListener('input', (e) => {
        const q = String(e.target.value || '').toLowerCase().trim();
        document.querySelectorAll('[data-scope-row]').forEach((row) => {
          const label = row.getAttribute('data-scope-label') || '';
          const key = row.getAttribute('data-scope-key') || '';
          row.classList.toggle('hidden', q !== '' && !label.includes(q) && !key.includes(q));
        });
      });
    })();
  </script>
</x-layouts.app>
