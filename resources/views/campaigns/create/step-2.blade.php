<x-campaigns.create-layout
  :step="2"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  previous-route="{{ route('campaigns.create.step', 1) }}"
  next-route="{{ route('campaigns.create.save', 2) }}"
>
  <form id="campaign-wizard-form" method="POST" action="{{ route('campaigns.create.save', 2) }}" data-validate-form class="flex w-full max-w-[677px] flex-col gap-5">
    @csrf
    <div class="flex flex-col gap-3">
      <p class="text-sm font-semibold leading-[1.4] text-text-primary">Choose a list for sending template</p>

      <label class="text-sm font-semibold leading-[1.4] text-text-primary" for="audience">
        To which list shall we send? <x-form.required />
      </label>
      <select
        id="audience"
        name="audience_id"
        required
        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
      >
        <option value="">-- Select Audience --</option>
        @foreach ($audiences ?? [] as $list)
          <option
            value="{{ $list->uuid }}"
            @selected(
              (string) old('audience_id') === (string) $list->uuid
              || (string) old('audience_id', $wizardData['audience_id'] ?? '') === (string) $list->id
            )
          >
            {{ $list->name }} ({{ $list->contacts_count }} contacts)
          </option>
        @endforeach
      </select>
    </div>
  </form>
</x-campaigns.create-layout>
