<x-templates.builder-layout active="submit" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null">
  <form method="post" action="{{ route('templates.builder.submit.save', $template) }}">
    @csrf
    <div class="flex w-full flex-col items-center gap-14 overflow-hidden rounded-xl bg-elevated p-14 shadow-[0px_0px_4px_0px_rgba(0,0,0,0.04)]">
      <div class="flex w-full flex-col items-center gap-8">
        <div class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#f2a356]">
          <img src="{{ asset('images/templates/submit-note-icon.svg') }}" alt="" class="size-8" width="32" height="32">
        </div>

        <div class="flex flex-col items-center gap-4 text-center">
          <h3 class="w-full max-w-[455px] text-xl font-semibold leading-[27px] text-[#4b4b4b]">Note</h3>
          <p class="w-full max-w-[350px] text-sm font-medium leading-[21px] text-[#98a0b4]">
            Please review your final changes here and submit your template for approval
          </p>
        </div>
      </div>

      <label class="flex cursor-pointer items-center gap-2 text-sm text-text-muted">
        <input type="checkbox" name="confirm" value="1" required>
        I confirm this template is ready for WhatsApp approval
      </label>

      <button
        type="submit"
        class="inline-flex items-center justify-center overflow-hidden rounded-xl px-6 py-3 text-center text-base font-semibold leading-[1.5] whitespace-nowrap text-white transition-opacity hover:opacity-90"
        style="background-image: linear-gradient(178.91deg, #6dbb48 0%, rgba(17, 153, 170, 0.557) 100%)"
      >
        Yes, Submit for approval
      </button>
    </div>
  </form>
</x-templates.builder-layout>

