@php
  $openModal = request('modal') === 'add-variable';
  $bodyText = old('body_text', $payload['body']['text'] ?? '');
  $samples = old('samples', $payload['body']['samples'] ?? []);
  $meta = $payload['meta'] ?? [];
  $setupComplete = $setupComplete ?? ($meta['setup_completed'] ?? false);
@endphp

@php
  $bodyLimit = (int) config('templates.body_limit', 1024);
  $buttonTextLimit = (int) config('templates.button_text_limit', 20);
  $maxButtons = (int) config('templates.max_buttons', 10);
  $maxUrlButtons = (int) config('templates.max_url_buttons', 2);
  $maxPhoneButtons = (int) config('templates.max_phone_buttons', 1);
@endphp

<x-templates.builder-layout
  active="body"
  :card="false"
  :template="$template"
  :payload="$payload"
  :preview-data="$previewData ?? null"
  :setup-complete="$setupComplete"
  :builder-steps="$builderSteps ?? null"
  :can-use-carousel="$canUseCarousel ?? false"
  body-form-id="builder-body-form"
>
  <form
    id="builder-body-form"
    method="post"
    action="{{ route('templates.builder.body.save', $template) }}"
    class="flex flex-col gap-4"
    data-validate-form
    data-ai-suggest-url="{{ $aiSuggestUrl ?? '' }}"
    data-variables-url="{{ route('templates.api.variables') }}"
    data-body-limit="{{ $bodyLimit }}"
    data-button-text-limit="{{ $buttonTextLimit }}"
    data-max-buttons="{{ $maxButtons }}"
    data-max-url-buttons="{{ $maxUrlButtons }}"
    data-max-phone-buttons="{{ $maxPhoneButtons }}"
  >
    @csrf

    <x-templates.utility-presets :presets="$utilityPresets ?? []" />

    <div class="rounded-lg bg-elevated p-2">
      <div class="relative flex w-full flex-col gap-3 rounded-xl border border-solid border-border bg-elevated p-3" data-validate-field>
        <label for="body_text" class="fd-label">Body<span class="text-[red]">*</span></label>
        <textarea
          id="body_text"
          name="body_text"
          rows="6"
          required
          maxlength="{{ $bodyLimit }}"
          class="min-h-[150px] w-full resize-y border-0 bg-transparent text-sm font-normal leading-[1.4] text-text-body focus:outline-none @error('body_text') border border-red-500 @enderror"
          placeholder="Write your message body..."
        >{{ $bodyText }}</textarea>
        <x-ui.field-error field="body_text" />
        {{-- <p class="text-xs text-text-subtle">Use <code>$(variable_name)</code> for dynamic variables. Pick from + Variable.</p> --}}

        <div class="flex flex-wrap items-center gap-2 border-t border-divider pt-2">
          <button type="button" data-editor-action="bold" class="template-editor-btn" title="Bold">B</button>
          <button type="button" data-editor-action="italic" class="template-editor-btn" title="Italic"><em>I</em></button>
          <button type="button" data-editor-action="strike" class="template-editor-btn" title="Strikethrough"><s>S</s></button>
          <button type="button" data-editor-action="emoji" class="template-editor-btn" title="Emoji">😀</button>
          <button type="button" data-editor-action="variable" class="template-editor-btn template-editor-btn--accent" title="Select Variable">+ Variable</button>
          @if (filled($aiSuggestUrl ?? null))
            <button type="button" data-editor-action="ai-suggest" class="template-editor-btn template-editor-btn--accent" title="AI Suggestions">AI</button>
          @endif
          <span data-body-char-count class="ml-auto text-xs text-text-subtle">0 / {{ $bodyLimit }}</span>
        </div>
      </div>
    </div>

    <div class="flex w-full items-center justify-end">
      <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90">
        Next
      </button>
    </div>

    <div id="variable-samples" class="flex flex-col gap-4"></div>
  </form>

  <x-templates.add-variable-modal
    :open="$openModal"
    :variables="$variables ?? collect()"
    :builtin-variables="$builtinVariables ?? []"
  />
  <x-templates.ai-suggestions-modal />
</x-templates.builder-layout>
