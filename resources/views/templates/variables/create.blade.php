@php
  $isEdit = $variable instanceof \App\Models\Variable;
  $selectedType = $form['data_type'] ?? 'string';
@endphp

<x-layouts.app :title="($isEdit ? 'Edit' : 'Create').' Variable - WapApp'" active="templates.variables">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col items-start justify-center p-4">
      <div class="flex w-full flex-col gap-1">
        <h1 class="fd-page-title">{{ $isEdit ? 'Edit Variable' : 'Create New Variable' }}</h1>
        <p class="fd-page-note max-w-[854px]">
          Variables are always created as dynamic placeholders. Choose a data type that matches how the value will be used in your templates.
        </p>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <form
        method="post"
        action="{{ $isEdit ? route('templates.variables.update', $variable) : route('templates.variables.store') }}"
        enctype="multipart/form-data"
        class="flex w-full flex-col gap-8 overflow-hidden rounded-xl border border-solid border-border-light bg-muted-surface p-4"
      >
        @csrf
        @if ($isEdit)
          @method('PUT')
        @endif

        <h2 class="fd-section-title">Select variable Type</h2>

        <div class="flex flex-wrap items-center gap-6">
          @foreach ($dataTypes as $type)
            <label class="cursor-pointer">
              <input
                type="radio"
                name="data_type"
                value="{{ $type->value }}"
                class="peer sr-only"
                @checked($selectedType === $type->value)
              >
              <span @class([
                'flex w-[140px] shrink-0 items-center justify-center rounded-lg px-5 py-2 text-sm font-medium leading-[1.4] shadow-[0px_0px_2px_rgba(0,0,0,0.04)]',
                'bg-green-500 text-primary-2 peer-checked:bg-green-500 peer-checked:text-primary-2',
                'border border-solid border-border-sidebar bg-elevated text-text-primary opacity-50 peer-checked:opacity-100 peer-checked:border-green-500',
              ])>
                {{ $type->label() }}
              </span>
            </label>
          @endforeach
        </div>
        @error('data_type')
          <p class="text-sm text-danger">{{ $message }}</p>
        @enderror

        <div class="flex w-full flex-col gap-6">
          <div class="flex w-full max-w-[474px] flex-col gap-3">
            <label for="variable_name" class="fd-label">Mention Variable Name</label>
            <input
              id="variable_name"
              name="name"
              type="text"
              value="{{ $form['name'] }}"
              placeholder="Enter Variable Name"
              required
              class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
            @error('name')
              <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
          </div>

          <div id="media-upload" class="{{ in_array($selectedType, ['image', 'video', 'pdf'], true) ? '' : 'hidden' }} flex w-full max-w-[474px] flex-col gap-3">
            <label for="variable_file" class="fd-label">Upload File</label>
            <input
              id="variable_file"
              name="file"
              type="file"
              class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted file:mr-3 file:rounded file:border-0 file:bg-green-500 file:px-3 file:py-1.5 file:text-primary-2"
            >
            @if ($isEdit && filled($variable->value) && $variable->data_type->isMedia())
              <p class="text-xs text-text-muted">Current file: {{ basename($variable->value) }}</p>
            @endif
            @error('file')
              <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
          </div>

          <div id="text-value" class="{{ in_array($selectedType, ['image', 'video', 'pdf'], true) ? 'hidden' : '' }} flex w-full max-w-[474px] flex-col gap-3">
            <label for="variable_value" class="fd-label">Default Value (optional)</label>
            <input
              id="variable_value"
              name="value"
              type="text"
              value="{{ $form['value'] }}"
              placeholder="Enter default value"
              class="fd-input w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
            @error('value')
              <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
          </div>

          <div class="flex w-full items-center justify-end">
            <div class="flex items-center gap-3">
              <a
                href="{{ route('templates.variables') }}"
                class="fd-btn inline-flex w-[120px] items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-green-500 transition-colors hover:bg-green-50"
              >
                Cancel
              </a>
              <button
                type="submit"
                class="fd-btn inline-flex w-[120px] items-center justify-center rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90"
              >
                {{ $isEdit ? 'Update' : 'Create' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </section>
  </div>

  <script>
    (() => {
      const mediaTypes = new Set(['image', 'video', 'pdf']);
      const mediaUpload = document.getElementById('media-upload');
      const textValue = document.getElementById('text-value');

      document.querySelectorAll('input[name="data_type"]').forEach((input) => {
        input.addEventListener('change', () => {
          const isMedia = mediaTypes.has(input.value);
          mediaUpload?.classList.toggle('hidden', !isMedia);
          textValue?.classList.toggle('hidden', isMedia);
        });
      });
    })();
  </script>
</x-layouts.app>
