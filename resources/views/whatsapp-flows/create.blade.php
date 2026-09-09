<x-layouts.app title="Create Flow - WapApp" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <a href="{{ route('whatsapp-flows.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to list</a>
        </div>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Create WhatsApp Flow</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Set up a new interactive form that your customers can fill out directly within WhatsApp.
        </p>
      </div>

      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="max-w-2xl rounded-xl bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <form action="{{ route('whatsapp-flows.store') }}" method="POST" class="flex flex-col gap-5">
          @csrf

          <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-semibold text-text-body">
              Flow Name <span class="text-red-500">*</span>
            </label>
            <input
              type="text"
              id="name"
              name="name"
              value="{{ old('name') }}"
              required
              placeholder="e.g. Customer Feedback Survey"
              class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm text-text-body focus:border-green-500 focus:outline-none"
            >
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-text-body">Categories <span class="text-red-500">*</span></span>
            <p class="text-xs text-text-muted">Meta requires at least one category.</p>
            <div class="grid grid-cols-2 gap-2 rounded-lg border border-divider bg-surface p-3">
              @foreach (config('whatsapp-flows.categories', ['OTHER']) as $category)
                <label class="flex items-center gap-2 text-xs text-text-body">
                  <input
                    type="checkbox"
                    name="categories[]"
                    value="{{ $category }}"
                    class="rounded border-divider"
                    @checked(collect(old('categories', ['OTHER']))->contains($category))
                  >
                  {{ str_replace('_', ' ', $category) }}
                </label>
              @endforeach
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('whatsapp-flows.index') }}" class="rounded-lg border border-divider bg-surface px-4 py-3 text-sm font-semibold text-text-body">Cancel</a>
            <button type="submit" class="rounded-lg bg-green-500 px-6 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90">Create &amp; Open Builder</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</x-layouts.app>
