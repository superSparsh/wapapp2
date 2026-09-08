<x-templates.builder-layout active="footer" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null">
  <form method="post" action="{{ route('templates.builder.footer.save', $template) }}" class="flex flex-col gap-4">
    @csrf
    <div class="rounded-lg bg-elevated p-2">
      <div class="relative flex h-[87px] w-full items-start rounded-xl border border-solid border-border bg-elevated p-3">
        <textarea
          name="footer_text"
          rows="2"
          class="min-w-0 flex-1 resize-none border-0 bg-transparent text-sm font-normal leading-[1.4] text-text-body focus:outline-none"
        >{{ old('footer_text', $payload['footer']['text'] ?? '') }}</textarea>
      </div>
    </div>

    <div class="flex w-full flex-col items-end justify-center">
      <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90">
        Next
      </button>
    </div>
  </form>
</x-templates.builder-layout>
