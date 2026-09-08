@props(['active' => 'body', 'title' => 'Create Template', 'card' => true, 'template' => null, 'payload' => [], 'previewData' => null, 'setupComplete' => null, 'bodyFormId' => null, 'builderSteps' => null, 'canUseCarousel' => false])

@php
  use App\Domains\Templates\Support\TemplateCategoryCatalog;
  use App\Domains\Templates\Support\TemplateLanguageCatalog;

  $meta = $payload['meta'] ?? [];
  $isSetupComplete = $setupComplete ?? ($meta['setup_completed'] ?? false);
  $categoryLabels = TemplateCategoryCatalog::labels();
  $editableMeta = ! $isSetupComplete && $active === 'body' && filled($bodyFormId);
  $displayName = old('name', $isSetupComplete ? ($template?->name ?? '') : ($meta['name'] ?: ''));
  $displayCategory = old('category', $isSetupComplete ? ($template?->category ?? 'MARKETING') : ($meta['category'] ?: 'MARKETING'));
  $displayLanguage = old('language', $isSetupComplete ? ($template?->language ?? 'en_GB') : ($meta['language'] ?: 'en_GB'));
  $displayType = old('template_type', $meta['template_type'] ?? 'regular');
  $templateName = $template?->name ?: ($meta['name'] ?? 'Template Name');
  $category = $template?->category ?: ($meta['category'] ?? 'MARKETING');
  $language = $template?->language ?: ($meta['language'] ?? 'en_GB');
  $templateType = $meta['template_type'] ?? 'regular';
  $headerType = $payload['header']['type'] ?? 'none';
  $previewDefaults = $previewData ?? [
    'header_type' => $headerType,
    'header_text' => $payload['header']['text'] ?? '',
    'header_image' => $headerType === 'image' ? asset('images/templates/preview-header-image.png') : null,
    'header_video' => null,
    'header_document' => null,
    'body' => $payload['body']['text'] ?? '',
    'footer' => $payload['footer']['text'] ?? '',
    'body_samples' => $payload['body']['samples'] ?? [],
    'buttons' => $payload['buttons'] ?? [],
    'button_mode' => $payload['button_mode'] ?? 'call_to_action',
    'is_opt_out' => $payload['is_opt_out'] ?? false,
    'rejection_reason' => $template?->rejection_reason,
    'carousel_cards' => $payload['carousel']['cards'] ?? [],
    'lto' => $payload['lto'] ?? [],
    'auth' => $payload['auth'] ?? [],
  ];
@endphp

<x-layouts.app :title="$title . ' - WapApp'" active="templates.index" :suppress-validation-toasts="true">
  <div
    class="flex flex-col bg-surface"
    data-template-builder
    data-template-step="{{ $active }}"
    data-preview-defaults='@json($previewDefaults)'
  >
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title">Create Template</h1>
        <p class="fd-page-note max-w-[854px]">
          Create personalized message templates for initiating conversation with your customers.
        </p>
      </div>

      <div class="flex flex-col gap-8">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">
          <div class="flex min-w-0 flex-col gap-3 lg:col-span-4">
            <label for="template_name" class="fd-label">
              Template Name
              @if ($editableMeta)
                <span class="text-[red]">*</span>
              @endif
            </label>
            @if ($editableMeta)
              <div data-validate-field>
                <input
                  id="template_name"
                  name="name"
                  form="{{ $bodyFormId }}"
                  type="text"
                  value="{{ $displayName }}"
                  required
                  pattern="[a-z0-9_]+"
                  oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')"
                  class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500 @error('name') border-red-500 @enderror"
                  placeholder="welcome_message"
                >
                <p class="mt-1 text-xs text-text-subtle">Only lowercase letters, numbers, and underscore (_) allowed</p>
                <x-ui.field-error field="name" />
              </div>
            @else
              <div class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted">
                {{ $templateName }}
              </div>
            @endif
          </div>

          <div class="flex min-w-0 flex-col gap-3 lg:col-span-3">
            <label for="template_category" class="fd-label">
              Category
              @if ($editableMeta)
                <span class="text-[red]">*</span>
              @endif
            </label>
            @if ($editableMeta)
              <div data-validate-field>
                <x-ui.select id="template_category" name="category" form="{{ $bodyFormId }}" variant="default" class="w-full" required>
                  @foreach ($categoryLabels as $categoryValue => $categoryLabel)
                    @if ($categoryValue === TemplateCategoryCatalog::CAROUSEL && ! $canUseCarousel)
                      <option value="{{ $categoryValue }}" disabled @selected($displayCategory === $categoryValue)>
                        {{ $categoryLabel }} (Advance plan required)
                      </option>
                    @else
                      <option value="{{ $categoryValue }}" @selected($displayCategory === $categoryValue)>{{ $categoryLabel }}</option>
                    @endif
                  @endforeach
                </x-ui.select>
                <x-ui.field-error field="category" />
              </div>
            @else
              <div class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted">
                {{ TemplateCategoryCatalog::label($category) }}
              </div>
            @endif
          </div>

          <div class="flex min-w-0 flex-col gap-3 lg:col-span-3">
            <label for="template_language" class="fd-label">
              Language
              @if ($editableMeta)
                <span class="text-[red]">*</span>
              @endif
            </label>
            @if ($editableMeta)
              <div data-validate-field>
                <x-ui.select id="template_language" name="language" form="{{ $bodyFormId }}" variant="default" class="w-full" required>
                  <option value="en_GB" @selected($displayLanguage === 'en_GB')>English (UK)</option>
                  <option value="en_US" @selected($displayLanguage === 'en_US')>English (US)</option>
                </x-ui.select>
                <x-ui.field-error field="language" />
              </div>
            @else
              <div class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted">
                {{ TemplateLanguageCatalog::label($language) }}
              </div>
            @endif
          </div>

          <div class="flex min-w-0 flex-col gap-3 lg:col-span-2">
            <label for="template_type" class="fd-label">
              Type
              @if ($editableMeta)
                <span class="text-[red]">*</span>
              @endif
            </label>
            @if ($editableMeta)
              <div data-validate-field>
                <x-ui.select id="template_type" name="template_type" form="{{ $bodyFormId }}" variant="default" class="w-full" required>
                  <option value="regular" @selected($displayType === 'regular')>Regular</option>
                  <option value="auto_response" @selected($displayType === 'auto_response')>Auto Response</option>
                </x-ui.select>
                <x-ui.field-error field="template_type" />
              </div>
            @else
              <div class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted">
                {{ $templateType === 'auto_response' ? 'Auto Response' : 'Regular' }}
              </div>
            @endif
          </div>
        </div>

        <x-templates.builder-nav :active="$active" :template="$template" :setup-complete="$isSetupComplete" :builder-steps="$builderSteps ?? null" />
      </div>
    </div>

    <div class="flex flex-col gap-6 px-4 pb-4 lg:flex-row lg:items-start lg:justify-between">
      <div @class(['min-w-0 w-full max-w-[725px]', 'rounded-lg bg-elevated p-2' => $card])>
        {{ $slot }}
      </div>

      <div class="w-full shrink-0 lg:w-[425px]">
        @if (isset($preview))
          {{ $preview }}
        @else
          <x-templates.phone-preview>
            <x-templates.message-preview-bubble :preview-data="$previewDefaults" live />
          </x-templates.phone-preview>
        @endif
      </div>
    </div>
  </div>
</x-layouts.app>
