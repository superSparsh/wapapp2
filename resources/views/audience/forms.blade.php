@php
    $behaviorToggles = [
        [
            'key' => 'show_required_only',
            'label' => 'Show only required fields',
            'hint_url' => $listFieldsUrl ?? null,
            'hint_label' => 'Manage list fields',
        ],
        ['key' => 'include_js', 'label' => 'Include javascript'],
        ['key' => 'include_css', 'label' => 'Include stylesheet'],
        ['key' => 'show_invisible_fields', 'label' => 'Show invisible field(s)'],
    ];
@endphp

<x-layouts.app title="Forms & Pages - WapApp" active="audience.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <x-audience.list-header :title="$mailList?->name ?? 'Forms / pages'" :subscribers="(string) ($subscriberCount ?? 0)" />
      <x-audience.sub-nav active="audience.forms" />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      @if (! $mailList)
        <div class="rounded-lg border border-dashed border-border bg-elevated p-8 text-center text-sm text-text-subtle">
          Open a list first, then use <span class="font-semibold text-text-primary">Forms / pages</span>.
        </div>
      @else
        <form
          method="POST"
          action="{{ route('audience.forms.update', ['list' => $mailList->id]) }}"
          class="bg-surface flex flex-col gap-4 items-start justify-center p-4 relative shrink-0 w-full"
          data-audience-forms
          data-autosave="true"
        >
          @csrf
          <input type="hidden" name="list" value="{{ $mailList->id }}">

          <div class="flex w-full items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
              <div class="text-[20px] font-semibold leading-[1.5] text-text-primary">Embedded form</div>
              <p class="text-sm text-text-muted">Paste this form on your website. Fixed WhatsApp subscribe fields — separate from Form Builder.</p>
            </div>
            <div class="flex items-center gap-3">
              <span data-forms-save-status class="text-xs font-medium text-text-muted"></span>
              <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-opacity hover:opacity-90">
                Save
              </button>
            </div>
          </div>

          <div class="flex flex-col gap-4 lg:flex-row w-full">
            <div class="bg-blue-50 flex flex-col gap-4 items-start p-3 rounded-[8px] shrink-0 w-full lg:w-[320px]">
              <div class="flex flex-col gap-2 w-full">
                <label for="form_title" class="text-[16px] font-semibold leading-[1.4] text-text-primary">
                  Form title <span class="text-red-500">*</span>
                </label>
                <input
                  id="form_title"
                  name="form_title"
                  type="text"
                  required
                  value="{{ old('form_title', $settings['form_title'] ?? '') }}"
                  placeholder="Subscribe to our WhatsApp list"
                  class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-[14px] font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                >
              </div>

              <div class="flex flex-col gap-3 w-full">
                <p class="text-[16px] font-semibold leading-[1.4] text-text-primary">Behavior</p>

                @foreach ($behaviorToggles as $toggle)
                  @php
                    $isActive = (bool) old($toggle['key'], $settings[$toggle['key']] ?? false);
                  @endphp
                  <div class="flex flex-col gap-1 w-full">
                    <div class="flex gap-[8px] items-center leading-[0] w-full">
                      <span class="flex flex-col font-semibold text-[14px] text-text-primary leading-[1.4]">
                        {{ $toggle['label'] }}
                      </span>
                      <span class="ml-auto flex items-center">
                        <input
                          type="hidden"
                          name="{{ $toggle['key'] }}"
                          value="{{ $isActive ? '1' : '0' }}"
                          data-behavior-input="{{ $toggle['key'] }}"
                        >
                        <x-ui.toggle-switch
                          :active="$isActive"
                          data-behavior-toggle="{{ $toggle['key'] }}"
                          aria-label="{{ $toggle['label'] }}"
                        />
                      </span>
                    </div>
                    @if (! empty($toggle['hint_url']))
                      <a href="{{ $toggle['hint_url'] }}" class="text-[12px] font-medium text-green-500 hover:underline">
                        {{ $toggle['hint_label'] ?? 'Manage fields' }}
                      </a>
                    @endif
                  </div>
                @endforeach
              </div>

              <div class="mt-1 w-full">
                <p class="text-[16px] font-semibold leading-[1.4] text-text-primary">Redirect</p>
                <div class="flex flex-col gap-2 w-full mt-2">
                  <label for="redirect_url" class="text-[14px] font-semibold leading-[1.4] text-text-primary whitespace-nowrap">
                    Custom redirect url
                  </label>
                  <input
                    id="redirect_url"
                    name="redirect_url"
                    type="text"
                    value="{{ old('redirect_url', $settings['redirect_url'] ?? '') }}"
                    placeholder="Leave blank for default thank-you page"
                    class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-[14px] font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                </div>
              </div>
            </div>

            <div class="bg-blue-50 flex flex-col gap-4 items-start p-3 rounded-[8px] shrink-0 flex-1 w-full lg:w-[500px]">
              <div class="flex flex-col gap-3 w-full">
                <div class="flex flex-col gap-2 w-full">
                  <p class="text-[16px] font-semibold leading-[1.4] text-text-primary">Copy / paste onto your site</p>
                  <p class="text-[14px] font-medium leading-[1.4] text-text-muted">HTML embed code for your website.</p>
                </div>

                <div class="bg-elevated border border-border border-solid flex gap-[12px] h-[280px] items-start justify-end p-[14px] relative rounded-[12px] shrink-0 w-full">
                  <textarea id="embed_code" data-embed-code readonly class="h-full w-full resize-none bg-transparent font-mono text-[12px] font-medium text-text-muted leading-[1.4] focus:outline-none">{{ $embedCode }}</textarea>
                  <button type="button" data-copy-embed class="bg-green-500 flex items-start justify-end px-[12px] py-[8px] rounded-[4px] shrink-0">
                    <span data-copy-embed-label class="text-[12px] font-semibold leading-[1.5] text-primary-2 whitespace-nowrap">Copy</span>
                  </button>
                </div>
              </div>

              <div class="h-px w-full bg-border my-2"></div>

              <div class="flex flex-col gap-3 w-full">
                <div class="flex flex-col gap-2 w-full">
                  <p class="text-[16px] font-semibold leading-[1.4] text-text-primary">Custom CSS</p>
                  <p class="text-[14px] font-medium leading-[1.4] text-text-muted">Optional styling for the embedded form</p>
                </div>

                <textarea
                  name="custom_css"
                  placeholder="/* optional CSS */"
                  class="bg-elevated border border-border min-h-[140px] w-full rounded-[12px] p-[14px] text-[14px] font-medium text-text-muted leading-[1.4] focus:border-green-500 focus:outline-none"
                >{{ old('custom_css', $settings['custom_css'] ?? '') }}</textarea>
              </div>
            </div>

            <div class="bg-blue-50 flex flex-col gap-2 items-start p-3 rounded-[8px] shrink-0 w-full lg:w-[320px]">
              <div class="flex items-start justify-between gap-4 w-full">
                <div class="min-w-0">
                  <p class="text-[16px] font-bold leading-[1.4] text-text-primary">Preview</p>
                  <p class="text-[14px] font-medium leading-[1.4] text-text-muted">Live embedded form</p>
                </div>
              </div>

              <div class="bg-elevated border border-border border-solid h-[593px] overflow-hidden relative rounded-[12px] shrink-0 w-full">
                @if ($previewUrl)
                  <iframe
                    data-forms-preview
                    src="{{ $previewUrl }}"
                    title="Embedded form preview"
                    class="h-full w-full border-0 bg-elevated"
                  ></iframe>
                @else
                  <p class="p-[14px] text-[14px] font-medium leading-[1.4] text-text-muted">Preview goes here</p>
                @endif
              </div>
            </div>
          </div>
        </form>
      @endif
    </section>
  </div>

  @if ($mailList)
    <script>
      (function () {
        const form = document.querySelector('[data-audience-forms]');
        if (!(form instanceof HTMLFormElement)) return;

        const embedCode = document.querySelector('[data-embed-code]');
        const preview = document.querySelector('[data-forms-preview]');
        const statusEl = document.querySelector('[data-forms-save-status]');
        let timer = null;
        let saving = false;

        const setStatus = (text, isError = false) => {
          if (!statusEl) return;
          statusEl.textContent = text || '';
          statusEl.classList.toggle('text-red-500', Boolean(isError && text));
          statusEl.classList.toggle('text-text-muted', !(isError && text));
        };

        const syncToggleVisual = (toggle, next) => {
          toggle.setAttribute('aria-checked', next ? 'true' : 'false');
          toggle.classList.toggle('bg-green-500', next);
          toggle.classList.toggle('bg-green-50', !next);
          const knob = toggle.querySelector('span');
          if (knob instanceof HTMLElement) {
            knob.classList.toggle('left-[24px]', next);
            knob.classList.toggle('left-[2px]', !next);
          }
        };

        const saveSettings = async () => {
          if (saving) return;
          saving = true;
          setStatus('Saving…');

          try {
            const token = form.querySelector('input[name="_token"]')?.value;
            const response = await fetch(form.action, {
              method: 'POST',
              headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
              },
              body: new FormData(form),
              credentials: 'same-origin',
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
              const msg = data?.message
                || (data?.errors ? Object.values(data.errors).flat()[0] : null)
                || 'Save failed';
              throw new Error(String(msg));
            }

            if (embedCode instanceof HTMLTextAreaElement && typeof data.embed_code === 'string') {
              embedCode.value = data.embed_code;
            }
            if (preview instanceof HTMLIFrameElement && typeof data.preview_url === 'string') {
              preview.src = data.preview_url;
            }
            setStatus('Saved');
            window.setTimeout(() => setStatus(''), 1500);
          } catch (err) {
            setStatus(err instanceof Error ? err.message : 'Save failed', true);
          } finally {
            saving = false;
          }
        };

        const scheduleSave = () => {
          if (form.dataset.autosave !== 'true') return;
          window.clearTimeout(timer);
          timer = window.setTimeout(() => saveSettings(), 450);
        };

        document.querySelector('[data-copy-embed]')?.addEventListener('click', async function (event) {
          event.preventDefault();
          event.stopPropagation();

          const el = document.getElementById('embed_code');
          const label = this.querySelector('[data-copy-embed-label]');
          const original = label?.textContent || 'Copy';
          const text = el instanceof HTMLTextAreaElement ? el.value : (el?.textContent || '');

          const mark = (msg) => {
            if (label) label.textContent = msg;
            window.setTimeout(() => {
              if (label) label.textContent = original;
            }, 1500);
          };

          try {
            if (navigator.clipboard?.writeText) {
              await navigator.clipboard.writeText(text);
            } else if (el instanceof HTMLTextAreaElement) {
              el.focus();
              el.select();
              document.execCommand('copy');
              el.setSelectionRange(0, 0);
            } else {
              throw new Error('Clipboard unavailable');
            }
            mark('Copied!');
          } catch (_) {
            if (el instanceof HTMLTextAreaElement) {
              try {
                el.focus();
                el.select();
                document.execCommand('copy');
                el.setSelectionRange(0, 0);
                mark('Copied!');
                return;
              } catch (__) {}
            }
            mark('Failed');
          }
        });

        document.querySelectorAll('[data-behavior-toggle]').forEach((toggle) => {
          toggle.addEventListener('click', (e) => {
            e.preventDefault();
            const key = toggle.getAttribute('data-behavior-toggle');
            const input = document.querySelector(`[data-behavior-input="${key}"]`);
            if (!(input instanceof HTMLInputElement)) return;
            const next = input.value !== '1';
            input.value = next ? '1' : '0';
            syncToggleVisual(toggle, next);
            scheduleSave();
          });
        });

        form.querySelectorAll('input[type="text"], textarea').forEach((input) => {
          input.addEventListener('input', scheduleSave);
          input.addEventListener('change', scheduleSave);
        });

        form.addEventListener('submit', (e) => {
          e.preventDefault();
          saveSettings();
        });
      })();
    </script>
  @endif
</x-layouts.app>
