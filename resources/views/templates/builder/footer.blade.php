@php
  $footerLimit = (int) config('templates.footer_limit', 60);
  $footerText = (string) old('footer_text', $payload['footer']['text'] ?? '');
@endphp

<x-templates.builder-layout
  active="footer"
  :card="false"
  :template="$template"
  :payload="$payload"
  :preview-data="$previewData ?? null"
  :builder-steps="$builderSteps ?? null"
  identity-form-id="builder-footer-form"
>
  <form id="builder-footer-form" method="post" action="{{ route('templates.builder.footer.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf
    <div class="rounded-lg bg-elevated p-2">
      <label for="footer_text" class="fd-label mb-2 block">Footer text</label>
      <div class="relative flex h-[87px] w-full items-start rounded-xl border border-solid border-border bg-elevated p-3">
        <textarea
          id="footer_text"
          name="footer_text"
          rows="2"
          maxlength="{{ $footerLimit }}"
          data-footer-limit="{{ $footerLimit }}"
          class="min-w-0 flex-1 resize-none border-0 bg-transparent text-sm font-normal leading-[1.4] text-text-body focus:outline-none @error('footer_text') outline outline-1 outline-red-500 @enderror"
          placeholder="Optional footer (max {{ $footerLimit }} characters)"
        >{{ $footerText }}</textarea>
      </div>
      <div class="mt-1 flex items-center justify-between gap-2">
        <p class="text-xs text-text-subtle">Maximum {{ $footerLimit }} characters (WhatsApp limit).</p>
        <span data-footer-char-count class="text-xs text-text-subtle">{{ mb_strlen($footerText) }} / {{ $footerLimit }}</span>
      </div>
      <x-ui.field-error field="footer_text" />
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>
</x-templates.builder-layout>
