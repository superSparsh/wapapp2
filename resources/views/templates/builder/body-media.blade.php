<x-templates.builder-layout active="body">
  <form class="flex flex-col gap-4 p-2">
    <div>
      <p class="fd-label">Media Type</p>
      <div class="mt-2 flex flex-wrap gap-5">
        @foreach ([['Image', true], ['Video', false], ['Document', false]] as [$label, $checked])
          <label class="flex cursor-pointer items-center gap-2">
            <img src="{{ asset('images/templates/' . ($checked ? 'radio-checked' : 'radio-unchecked') . '.svg') }}" alt="" class="size-4" aria-hidden="true">
            <span @class([
              'fd-input text-base',
              'text-green-500' => $checked,
              'text-blue-200' => ! $checked,
            ])>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>

    <x-templates.upload-zone hint="PNG, JPG up to 5MB" />

    <div>
      <label class="fd-label">Caption</label>
      <textarea rows="3" class="fd-input mt-3 w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5">Check out our Indiawood 2024 showcase!</textarea>
    </div>

    <div class="flex justify-end pt-2">
      <a href="{{ route('templates.builder.footer') }}" class="fd-btn fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-2 text-primary-2">
        Next
      </a>
    </div>
  </form>

  <x-slot:preview>
    <x-templates.phone-preview>
      <div class="max-w-[85%] overflow-hidden rounded-lg rounded-tl-none bg-elevated shadow-sm">
        <div class="flex h-32 items-center justify-center bg-border fd-table-cell">indiawood_2024_promo.jpg</div>
        <div class="fd-table-cell p-3">Check out our Indiawood 2024 showcase!</div>
      </div>
    </x-templates.phone-preview>
  </x-slot:preview>
</x-templates.builder-layout>
