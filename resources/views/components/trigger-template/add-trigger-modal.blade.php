@props([
  'open' => false,
  'closeHref' => null,
  'templateOptions' => [],
  'mailListOptions' => [],
])

<div
  id="modal-add-trigger"
  data-modal="add-trigger"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-add-trigger"
>
  <div class="flex max-h-[90vh] w-full max-w-[900px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-add-trigger" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Add New Trigger
        </h2>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @else
        <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      @endif
    </div>

    <form method="post" action="{{ route('trigger-template.store') }}" class="flex flex-col gap-4">
      @csrf

      <div class="flex flex-col items-start gap-6 lg:flex-row lg:gap-8">
        <div class="flex w-full flex-col gap-4 lg:w-[401px] lg:shrink-0">
          <h3 class="text-xl font-semibold leading-[1.5] text-text-primary">Create Trigger</h3>

          @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
              <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-3">
              <label for="template_code" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Select Template
              </label>
              <div class="relative">
                <select
                  id="template_code"
                  name="template_code"
                  required
                  class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('template_code') border-red-500 @enderror"
                >
                  <option value="" disabled @selected(old('template_code') === null)>Select template</option>
                  @foreach ($templateOptions as $template)
                    <option
                      value="{{ $template['code'] }}"
                      data-name="{{ $template['name'] }}"
                      data-preview="{{ e(json_encode($template['preview'] ?? ['body' => $template['body'] ?? ''], JSON_UNESCAPED_UNICODE)) }}"
                      @selected(old('template_code') === $template['code'])
                    >
                      {{ $template['name'] }}
                    </option>
                  @endforeach
                </select>
                <img
                  src="{{ asset('images/commerce/arrow-down.svg') }}"
                  alt=""
                  class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                  width="16"
                  height="16"
                >
              </div>
              <input type="hidden" name="template_name" id="template_name" value="{{ old('template_name') }}">
              @error('template_code')
                <p class="text-xs text-red-600">{{ $message }}</p>
              @enderror
              @if (count($templateOptions) === 0)
                <p class="text-xs font-medium leading-[1.4] text-text-muted">
                  No approved templates found. Connect a WhatsApp line and sync templates first.
                </p>
              @endif
            </div>

            <div class="flex flex-col gap-3">
              <label for="variable_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Trigger Name
              </label>
              <input
                id="variable_name"
                name="variable_name"
                type="text"
                value="{{ old('variable_name') }}"
                required
                pattern="[A-Za-z0-9_]+"
                placeholder="Enter Trigger Name"
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('variable_name') border-red-500 @enderror"
              >
              @error('variable_name')
                <p class="text-xs text-red-600">{{ $message }}</p>
              @enderror
              <p class="text-xs font-medium leading-[1.4] text-text-muted">
                To trigger on the first message from a new contact, use the trigger name
                <code class="font-mono">{{ config('trigger-template.any_message_trigger') }}</code>.
              </p>
            </div>

            @if (count($mailListOptions) > 0)
              <div class="flex flex-col gap-3">
                <label for="list_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Mail list
                </label>
                <div class="relative">
                  <select
                    id="list_id"
                    name="list_id"
                    class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                    <option value="">No list</option>
                    @foreach ($mailListOptions as $list)
                      <option value="{{ $list['id'] }}" @selected((string) old('list_id') === (string) $list['id'])>
                        {{ $list['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <img
                    src="{{ asset('images/commerce/arrow-down.svg') }}"
                    alt=""
                    class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                    width="16"
                    height="16"
                  >
                </div>
              </div>
            @endif
          </div>
        </div>

        <div class="w-full shrink-0 lg:w-[320px]">
          <x-templates.phone-preview title="Template Preview" size="compact">
            <div
              id="trigger-template-preview"
              class="mx-auto flex w-full max-w-full flex-col gap-3 rounded-br-[12px] rounded-tl-[12px] rounded-tr-[12px] border border-solid border-border bg-elevated p-2"
            >
              <img
                data-preview-header-image
                src=""
                alt=""
                class="hidden aspect-[1600/800] w-full rounded object-cover"
                width="338"
                height="169"
              >
              <video
                data-preview-header-video
                src=""
                class="hidden max-h-40 w-full rounded object-cover"
                controls
                playsinline
              ></video>
              <p
                data-preview-header-text
                class="hidden w-full text-sm font-semibold leading-[1.4] text-text-body"
              ></p>
              <div
                data-preview-body
                class="wa-preview-body w-full text-sm font-normal leading-[1.4] text-text-body"
              >Select a template to preview the message.</div>
              <p
                data-preview-footer
                class="hidden w-full text-xs font-normal leading-[1.4] text-text-subtle"
              ></p>
              <img
                data-preview-divider
                src="{{ asset('images/templates/message-divider.svg') }}"
                alt=""
                class="hidden w-full"
                width="338"
                height="1"
              >
              <div data-preview-buttons class="flex w-full flex-col"></div>
            </div>
          </x-templates.phone-preview>
        </div>
      </div>

      <div class="flex w-full items-center justify-end gap-2.5">
        @if (! empty($closeHref))
          <a
            href="{{ $closeHref }}"
            class="fd-btn-sm inline-flex items-center justify-center rounded border border-solid border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </a>
        @else
          <button
            type="button"
            data-modal-close
            class="fd-btn-sm inline-flex items-center justify-center rounded border border-solid border-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </button>
        @endif
        <button
          type="submit"
          class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-6 py-3 text-xs font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
          @disabled(count($templateOptions) === 0)
        >
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (() => {
    const select = document.getElementById('template_code');
    const nameInput = document.getElementById('template_name');
    const previewRoot = document.getElementById('trigger-template-preview');

    if (!select || !nameInput || !previewRoot) {
      return;
    }

    const escapeHtml = (value) => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

    const formatWhatsApp = (text) => {
      let html = escapeHtml(text || '');
      html = html.replace(/```([^`]+)```/g, '<code class="wa-mono">$1</code>');
      html = html.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
      html = html.replace(/\^([^\^\n]+)\^/g, '<strong>$1</strong>');
      html = html.replace(/(?<![A-Za-z0-9])_([^_\n]+)_(?![A-Za-z0-9])/g, '<em>$1</em>');
      html = html.replace(/~([^~\n]+)~/g, '<del>$1</del>');

      return html.replace(/\n/g, '<br>');
    };

    const buttonIcon = (type) => {
      if (type === 'phone') return @json(asset('images/templates/call.svg'));
      if (type === 'flow') return @json(asset('images/templates/flow.svg'));
      if (type === 'quick_reply') return @json(asset('images/templates/quick-reply.svg'));

      return @json(asset('images/templates/export.svg'));
    };

    const applyPreview = (data) => {
      const headerType = data?.header_type || 'none';
      const headerImage = previewRoot.querySelector('[data-preview-header-image]');
      const headerVideo = previewRoot.querySelector('[data-preview-header-video]');
      const headerText = previewRoot.querySelector('[data-preview-header-text]');
      const body = previewRoot.querySelector('[data-preview-body]');
      const footer = previewRoot.querySelector('[data-preview-footer]');
      const buttons = previewRoot.querySelector('[data-preview-buttons]');
      const divider = previewRoot.querySelector('[data-preview-divider]');

      const showImage = headerType === 'image' && !!data?.header_image;
      const showVideo = headerType === 'video' && !!(data?.header_video || data?.header_image);
      const showHeaderText = (headerType === 'text' || headerType === 'location')
        && !!(data?.header_text || '').trim();

      if (headerImage) {
        headerImage.classList.toggle('hidden', !showImage);
        if (showImage) headerImage.src = data.header_image;
      }

      if (headerVideo) {
        headerVideo.classList.toggle('hidden', !showVideo);
        if (showVideo) headerVideo.src = data.header_video || data.header_image || '';
      }

      if (headerText) {
        headerText.classList.toggle('hidden', !showHeaderText);
        headerText.textContent = data?.header_text || '';
      }

      if (body) {
        const rawBody = (data?.body || '').trim();
        body.innerHTML = rawBody
          ? formatWhatsApp(rawBody)
          : escapeHtml('Select a template to preview the message.');
      }

      if (footer) {
        const footerValue = (data?.footer || '').trim();
        footer.classList.toggle('hidden', footerValue === '');
        footer.textContent = footerValue;
      }

      const buttonItems = Array.isArray(data?.buttons) ? data.buttons : [];
      if (divider) divider.classList.toggle('hidden', buttonItems.length === 0);

      if (buttons) {
        buttons.innerHTML = buttonItems.map((button) => {
          const text = escapeHtml(button?.text || 'Button');
          const icon = buttonIcon(button?.type || 'url');

          return `<div class="flex w-full items-center justify-center gap-2 py-1">
            <img src="${icon}" alt="" class="size-4 shrink-0" width="16" height="16">
            <span class="text-sm font-medium leading-[1.4] whitespace-nowrap text-link-green">${text}</span>
          </div>`;
        }).join('');
      }
    };

    const sync = () => {
      const option = select.options[select.selectedIndex];
      const name = option?.dataset?.name || option?.textContent?.trim() || '';
      nameInput.value = name;

      if (!option || !option.value) {
        applyPreview(null);
        return;
      }

      let preview = null;
      try {
        preview = JSON.parse(option.dataset.preview || '{}');
      } catch (_error) {
        preview = { body: name };
      }

      applyPreview(preview && typeof preview === 'object' ? preview : { body: name });
    };

    select.addEventListener('change', sync);
    sync();
  })();
</script>
