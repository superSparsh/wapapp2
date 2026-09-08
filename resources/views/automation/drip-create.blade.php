<x-layouts.app title="Create Drip Campaign - WapApp" active="automation.drip.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <a href="{{ route('automation.drip.index') }}" class="text-sm font-medium text-green-500 hover:underline">&larr; Back to Drip Marketing</a>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Create Drip Campaign</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Set a name and trigger to start building your automation flow.
        </p>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <form
        method="POST"
        action="{{ route('automation.drip.store') }}"
        class="flex max-w-[600px] flex-col gap-4 rounded-xl bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]"
        data-drip-settings-form
        novalidate
      >
        @csrf

        <div class="flex flex-col gap-2">
          <label for="name" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Automation name <x-form.required />
          </label>
          <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name') }}"
            required
            minlength="2"
            maxlength="191"
            class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium text-text-body focus:outline-none focus:ring-2 focus:ring-green-500 @error('name') border-red-500 @enderror"
            placeholder="Enter campaign name"
          >
          @error('name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        <x-automation.drip-trigger-select :selected-type="old('trigger_type', 'welcome-new-subscriber')" />

        <div class="flex items-stretch gap-2">
          <button
            type="submit"
            class="fd-btn flex flex-1 items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
          >
            Create &amp; Design Flow
          </button>
          <a
            href="{{ route('automation.drip.index') }}"
            class="fd-btn flex items-center justify-center rounded border border-solid border-border-light px-4 py-3 text-sm font-semibold text-text-body hover:bg-surface"
          >
            Cancel
          </a>
        </div>
      </form>
    </section>
  </div>

  @push('scripts')
    <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
