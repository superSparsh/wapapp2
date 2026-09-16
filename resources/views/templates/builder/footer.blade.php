<x-templates.builder-layout
  active="footer"
  :card="false"
  :template="$template"
  :payload="$payload"
  :preview-data="$previewData ?? null"
  :builder-steps="$builderSteps ?? null"
>
  <form method="post" action="{{ route('templates.builder.footer.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf
    <div class="rounded-lg bg-elevated p-2">
      <div class="relative flex h-[87px] w-full items-start rounded-xl border border-solid border-border bg-elevated p-3">
        <textarea
          name="footer_text"
          rows="2"
          maxlength="{{ (int) config('templates.footer_limit', 60) }}"
          class="min-w-0 flex-1 resize-none border-0 bg-transparent text-sm font-normal leading-[1.4] text-text-body focus:outline-none @error('footer_text') outline outline-1 outline-red-500 @enderror"
        >{{ old('footer_text', $payload['footer']['text'] ?? '') }}</textarea>
      </div>
      <x-ui.field-error field="footer_text" />
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>
</x-templates.builder-layout>
