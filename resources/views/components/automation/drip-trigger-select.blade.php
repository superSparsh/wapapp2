@props([
    'selectedType' => 'welcome-new-subscriber',
    'triggerOptions' => [],
    'campaign' => null,
])

@php
  use App\Domains\Drip\Support\DripTriggerCatalog;

  $normalizedType = DripTriggerCatalog::normalizeType(old('trigger_type', $selectedType));
  $options = old('trigger_options', $triggerOptions ?? []);
  $grouped = DripTriggerCatalog::groupedOptions();
  $catalog = config('drip-triggers.types', []);
  $delayOptions = config('drip-triggers.delay_before_options', []);
  $daysOfWeek = config('drip-triggers.days_of_week', []);
  $selectedMeta = DripTriggerCatalog::type($normalizedType) ?? [];
@endphp

<div class="flex flex-col gap-3" data-drip-trigger-select>
  <div class="flex flex-col gap-2">
    <label for="trigger_type" class="flex items-center gap-1.5 text-sm font-semibold leading-[1.4] text-text-primary">
      <span>Automation Trigger</span>
      <span class="relative inline-flex" data-drip-trigger-info-wrap>
        <button
          type="button"
          class="inline-flex rounded-full text-text-subtle transition-colors hover:text-text-body focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
          data-drip-trigger-info-btn
          aria-label="About automation triggers"
          aria-expanded="false"
        >
          <img src="{{ asset('images/form-builder/info-circle.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
        </button>
        <div
          data-drip-trigger-info-panel
          role="tooltip"
          class="absolute top-full left-1/2 z-30 mt-2 hidden w-72 max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-xl border border-solid border-green-100 bg-green-50/95 p-3 text-xs font-normal leading-[1.5] text-text-subtle shadow-lg"
        >
          {{ config('drip-triggers.intro') }}
        </div>
      </span>
      <x-form.required />
    </label>
    <select
      id="trigger_type"
      name="trigger_type"
      data-drip-trigger-type
      required
      class="w-full rounded-xl border border-solid border-border bg-elevated p-3.5 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_type') border-red-500 @enderror"
    >
      @foreach ($grouped as $groupLabel => $items)
        <optgroup label="{{ $groupLabel }}">
          @foreach ($items as $value => $label)
            <option value="{{ $value }}" @selected($normalizedType === $value)>{{ $label }}</option>
          @endforeach
        </optgroup>
      @endforeach
    </select>
    @error('trigger_type') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
  </div>

  <div
    data-drip-trigger-detail
    class="flex flex-col gap-3 rounded-xl border border-solid border-border bg-elevated p-4"
  >
    <div>
      <p data-drip-trigger-title class="text-sm font-semibold leading-[1.4] text-text-primary">
        {{ $selectedMeta['label'] ?? 'Automation trigger' }}
      </p>
      <p data-drip-trigger-description class="mt-1 text-xs font-medium leading-[1.4] text-text-subtle">
        {{ $selectedMeta['description'] ?? '' }}
      </p>
      <p data-drip-trigger-intro class="mt-2 text-sm font-normal leading-[1.5] text-text-body">
        {{ $selectedMeta['intro'] ?? '' }}
      </p>
    </div>

    @foreach ($catalog as $typeKey => $meta)
      @php
        $fields = $meta['fields'] ?? [];
        $isActive = $typeKey === $normalizedType;
      @endphp
      <div
        data-drip-trigger-panel="{{ $typeKey }}"
        @class(['flex flex-col gap-3', 'hidden' => ! $isActive])
      >
        @if (in_array('before', $fields, true) || in_array('delay', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">
              {{ in_array('delay', $fields, true) ? 'Delay' : 'Before' }} <x-form.required />
            </label>
            <select
              name="trigger_options[{{ in_array('delay', $fields, true) ? 'delay' : 'before' }}]"
              required
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              @foreach ($delayOptions as $value => $label)
                <option
                  value="{{ $value }}"
                  @selected(($options[in_array('delay', $fields, true) ? 'delay' : 'before'] ?? '0 day') === $value)
                >
                  {{ $label }}
                </option>
              @endforeach
            </select>
          </div>
        @endif

        @if (in_array('date', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Date <x-form.required /></label>
            <input
              type="date"
              name="trigger_options[date]"
              value="{{ $options['date'] ?? '' }}"
              required
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_options.date') border-red-500 @enderror"
            >
            @error('trigger_options.date') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        @endif

        @if (in_array('at', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Time <x-form.required /></label>
            <input
              type="time"
              name="trigger_options[at]"
              value="{{ $options['at'] ?? '10:00' }}"
              required
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_options.at') border-red-500 @enderror"
            >
            @error('trigger_options.at') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        @endif

        @if (in_array('field', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Date field <x-form.required /></label>
            <input
              type="text"
              name="trigger_options[field]"
              value="{{ $options['field'] ?? 'date_of_birth' }}"
              required
              maxlength="64"
              placeholder="e.g. date_of_birth"
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_options.field') border-red-500 @enderror"
            >
            @error('trigger_options.field') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        @endif

        @if (in_array('tag_name', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Tag name <x-form.required /></label>
            <input
              type="text"
              name="trigger_options[tag_name]"
              value="{{ $options['tag_name'] ?? '' }}"
              required
              maxlength="64"
              placeholder="Enter tag name"
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_options.tag_name') border-red-500 @enderror"
            >
            @error('trigger_options.tag_name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        @endif

        @if (in_array('days_of_week', $fields, true))
          @php
            $selectedDays = array_map('intval', (array) ($options['days_of_week'] ?? []));
          @endphp
          <div class="flex flex-col gap-2" data-drip-trigger-checkbox-group="days_of_week">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Days of week <x-form.required /></label>
            <div class="flex flex-wrap gap-2">
              @foreach ($daysOfWeek as $dayValue => $dayLabel)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-solid border-border px-3 py-2 text-xs font-medium text-text-body has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                  <input
                    type="checkbox"
                    name="trigger_options[days_of_week][]"
                    value="{{ $dayValue }}"
                    data-drip-trigger-checkbox
                    @checked(in_array((int) $dayValue, $selectedDays, true))
                    class="size-3.5 rounded border-border text-green-500 focus:ring-green-500"
                  >
                  {{ $dayLabel }}
                </label>
              @endforeach
            </div>
            @error('trigger_options.days_of_week') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <p class="hidden text-xs text-red-500" data-drip-trigger-checkbox-error>Select at least one day of the week.</p>
          </div>
        @endif

        @if (in_array('days_of_month', $fields, true))
          @php
            $selectedMonthDays = array_map('intval', (array) ($options['days_of_month'] ?? []));
          @endphp
          <div class="flex flex-col gap-2" data-drip-trigger-checkbox-group="days_of_month">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">Days of month <x-form.required /></label>
            <div class="flex flex-wrap gap-1.5">
              @for ($day = 1; $day <= 31; $day++)
                <label class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg border border-solid border-border text-xs font-medium text-text-body has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                  <input
                    type="checkbox"
                    name="trigger_options[days_of_month][]"
                    value="{{ $day }}"
                    data-drip-trigger-checkbox
                    @checked(in_array($day, $selectedMonthDays, true))
                    class="sr-only"
                  >
                  {{ $day }}
                </label>
              @endfor
            </div>
            @error('trigger_options.days_of_month') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <p class="hidden text-xs text-red-500" data-drip-trigger-checkbox-error>Select at least one day of the month.</p>
          </div>
        @endif

        @if (in_array('woo_source', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">WooCommerce store <x-form.required /></label>
            <input
              type="text"
              name="trigger_options[woo_source]"
              value="{{ $options['woo_source'] ?? '' }}"
              required
              maxlength="191"
              placeholder="Connect WooCommerce in Integrations first"
              class="w-full rounded-xl border border-solid border-border bg-surface p-3 text-sm font-medium text-text-muted focus:outline-none focus:ring-2 focus:ring-green-500 @error('trigger_options.woo_source') border-red-500 @enderror"
            >
            <p class="text-xs leading-[1.4] text-text-subtle">
              Connect your WooCommerce store under Connected Apps to enable abandoned cart triggers.
            </p>
            @error('trigger_options.woo_source') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
          </div>
        @endif

        @if (in_array('api_endpoint', $fields, true))
          <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-[1.4] text-text-primary">API endpoint</label>
            @if ($campaign)
              <div class="rounded-xl border border-dashed border-border bg-surface p-3">
                <code class="block break-all text-xs text-text-body">POST {{ url('/api/drip/'.$campaign->uuid.'/trigger') }}</code>
              </div>
              <p class="text-xs leading-[1.4] text-text-subtle">
                Use your API token to trigger this automation for contacts in the selected audience.
              </p>
            @else
              <p class="text-xs leading-[1.4] text-text-subtle">
                Save the campaign first to view your unique API trigger endpoint.
              </p>
            @endif
          </div>
        @endif

        @if ($fields === [])
          <p class="text-xs leading-[1.4] text-text-subtle">
            Select an audience above. This trigger uses your chosen audience list automatically.
          </p>
        @endif
      </div>
    @endforeach
  </div>

  <script type="application/json" data-drip-trigger-catalog>@json($catalog)</script>
</div>
