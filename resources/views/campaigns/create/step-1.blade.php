<x-campaigns.create-layout
  :step="1"
  :campaign-name="$wizardData['name'] ?? 'New Campaign'"
  next-route="{{ route('campaigns.create.save', 1) }}"
>
  <form id="campaign-wizard-form" method="POST" action="{{ route('campaigns.create.save', 1) }}" data-validate-form class="flex w-full max-w-[677px] flex-col gap-5">
    @csrf
    <div class="flex flex-col gap-2">
      <label class="text-sm font-semibold leading-[1.4] text-text-primary" for="campaign-name">
        Campaign Name <x-form.required />
      </label>
      <input
        id="campaign-name"
        name="name"
        type="text"
        required
        minlength="2"
        value="{{ old('name', $wizardData['name'] ?? '') }}"
        placeholder="Enter campaign name"
        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
      >
      @error('name') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-sm font-semibold leading-[1.4] text-text-primary" for="from-number">
        From Number <x-form.required />
      </label>
      <select
        id="from-number"
        name="whatsapp_line_id"
        required
        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
      >
        <option value="">Choose</option>
        @foreach ($whatsappLines ?? [] as $line)
          <option value="{{ $line->id }}" @selected(old('whatsapp_line_id', $wizardData['whatsapp_line_id'] ?? '') == $line->id)>
            +{{ ltrim((string) $line->phone, '+') }}@if (filled($line->display_name)) — {{ $line->display_name }}@endif
          </option>
        @endforeach
      </select>
      @error('whatsapp_line_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
    </div>
  </form>
</x-campaigns.create-layout>
