<x-layouts.app title="Drip Design - WapApp" active="automation.drip.index" mainOverflow="overflow-hidden">
  <section class="flex h-full min-h-0 flex-col bg-surface lg:flex-row lg:items-stretch" data-drip-workspace>
    <x-automation.drip-flow-canvas
      :campaign="$campaign"
      :showMaximize="true"
      :editable="true"
      :fillHeight="true"
      :nodeTypes="$nodeTypes"
      :nodeOptions="$nodeOptions"
      :categories="$categories"
      :save-url="route('automation.drip.flow.save', $campaign)"
      :load-url="route('automation.drip.flow.data', $campaign)"
      :templates-list-url="route('templates.api.list')"
      :audiences="$audiences"
    />

    <div class="min-w-0 flex-1 overflow-y-auto" data-drip-side-panel>
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="settings">
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          @if (session('status'))
            <div class="rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
              {{ session('status') }}
            </div>
          @endif

          <form
            method="POST"
            action="{{ route('automation.drip.design.update', $campaign) }}"
            class="flex flex-col gap-4"
            data-drip-settings-form
            novalidate
          >
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-3">
              <p class="text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
                Below is general information of the automation. You can update the settings and click `Save` button.
              </p>
              <label for="automation_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Automation name <x-form.required />
              </label>
              <div class="flex w-full items-center gap-3 rounded-xl border border-solid border-border bg-elevated p-3.5 @error('name') border-red-500 @enderror">
                <input
                  id="automation_name"
                  name="name"
                  type="text"
                  value="{{ old('name', $campaign->name) }}"
                  required
                  minlength="2"
                  maxlength="191"
                  class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-muted focus:outline-none"
                >
              </div>
              @error('name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-3">
              <x-automation.drip-trigger-select
                :selected-type="$campaign->trigger_type"
                :trigger-options="$campaign->trigger_options ?? []"
                :campaign="$campaign"
              />
            </div>

            <div class="flex flex-col gap-3">
              <label for="audience_id" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Audience <x-form.required />
              </label>
              <select
                id="audience_id"
                name="audience_id"
                required
                class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('audience_id') border-red-500 @enderror"
              >
                <option value="" disabled @selected(! old('audience_id', $campaign->audience?->uuid))>-- Select Audience --</option>
                @foreach ($audiences as $list)
                  <option
                    value="{{ $list->uuid }}"
                    @selected(
                      (string) old('audience_id') === (string) $list->uuid
                      || (string) old('audience_id', $campaign->audience?->uuid) === (string) $list->uuid
                    )
                  >
                    {{ $list->name }}
                  </option>
                @endforeach
              </select>
              @error('audience_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-3">
              <label for="timezone" class="text-sm font-semibold leading-[1.4] text-text-primary">
                Time zone <x-form.required />
              </label>
              <input
                type="search"
                placeholder="Search timezone..."
                class="w-full rounded-xl border border-solid border-border bg-elevated p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500"
                data-tz-search
                autocomplete="off"
              >
              <select
                id="timezone"
                name="timezone"
                size="6"
                required
                class="w-full rounded-xl border border-solid border-border bg-elevated p-2 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('timezone') border-red-500 @enderror"
                data-tz-list
              >
                @foreach ($timezoneOptions as $option)
                  <option
                    value="{{ $option['value'] }}"
                    data-tz-option
                    @selected(old('timezone', $campaign->timezone) === $option['value'])
                  >
                    {{ $option['label'] }}
                  </option>
                @endforeach
              </select>
              @error('timezone') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-4">
              <div class="flex min-w-0 flex-1 flex-col gap-3">
                <label for="start_date" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  Start Date <x-form.required />
                </label>
                <input
                  id="start_date"
                  type="date"
                  name="start_date"
                  value="{{ old('start_date', $campaign->start_date?->format('Y-m-d')) }}"
                  required
                  class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('start_date') border-red-500 @enderror"
                >
                @error('start_date') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
              </div>
              <div class="flex min-w-0 flex-1 flex-col gap-3">
                <label for="end_date" class="text-sm font-semibold leading-[1.4] text-text-primary">
                  End Date <x-form.required />
                </label>
                <input
                  id="end_date"
                  type="date"
                  name="end_date"
                  value="{{ old('end_date', $campaign->end_date?->format('Y-m-d')) }}"
                  required
                  class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('end_date') border-red-500 @enderror"
                >
                @error('end_date') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
              </div>
            </div>

            <div class="flex items-stretch gap-2">
              <button
                type="submit"
                class="fd-btn flex flex-1 items-center justify-center self-stretch rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
              >
                Save
              </button>
            </div>
          </form>

          <form
            method="POST"
            action="{{ route('automation.drip.destroy', $campaign) }}"
            data-drip-delete
            data-drip-redirect="{{ route('automation.drip.index') }}"
            data-confirm="Permanently delete this automation and all associated data?"
            data-confirm-title="Delete automation"
            data-confirm-label="Delete"
            data-confirm-variant="danger"
          >
            @csrf
            @method('DELETE')
            <div class="flex items-stretch gap-2">
              <button
                type="submit"
                class="flex shrink-0 items-center justify-center rounded bg-[red] px-4 py-3"
                aria-label="Delete automation"
              >
                <img src="{{ asset('images/automation/trash-white.svg') }}" alt="" class="size-5" width="20" height="20">
              </button>
            </div>
          </form>

          <div class="flex w-full flex-col items-center justify-center rounded-lg border border-solid border-[red] bg-[rgba(255,0,0,0.1)] p-3">
            <p class="w-full text-sm font-normal leading-[1.4] text-[red] opacity-60">
              Click to permanently delete this automation and all associated data. This action cannot be undone.
            </p>
          </div>
        </div>
      </x-automation.drip-campaign-header>
    </div>
  </section>

  <div
    data-drip-node-config-panel
    class="fixed inset-y-0 right-0 z-[70] hidden w-full max-w-[380px] overflow-y-auto bg-elevated shadow-[-4px_0px_12px_rgba(0,0,0,0.1)]"
    role="dialog"
    aria-modal="true"
    aria-labelledby="drip-config-title"
  >
    <div class="flex items-center justify-between border-b border-divider p-4">
      <h3 id="drip-config-title" data-drip-config-title class="text-base font-semibold text-text-primary">Configure Node</h3>
      <button type="button" data-drip-config-close class="text-2xl leading-none text-text-muted hover:text-text-body" aria-label="Close">&times;</button>
    </div>
    <div data-drip-config-body class="p-4"></div>
  </div>

  @push('scripts')
    <script src="{{ asset('js/automation/node-config.js') }}" defer></script>
    <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
    <script src="{{ asset('js/drip/drip-flow-editor.js') }}" defer></script>
  @endpush
</x-layouts.app>
