@php
  $lto = $payload['lto'] ?? [];
@endphp

<x-templates.builder-layout active="lto" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null">
  <form method="post" action="{{ route('templates.builder.lto.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-4">
      <p class="text-sm text-text-subtle">Limited Time Offer templates include a special offer component. Add a copy-code button in the Buttons step.</p>

      <div class="flex flex-col gap-2">
        <label for="discount_introduction" class="fd-label">Offer text<span class="text-[red]">*</span></label>
        <input id="discount_introduction" name="discount_introduction" type="text" maxlength="60" value="{{ old('discount_introduction', $lto['discount_introduction'] ?? '') }}" placeholder="20% off this week only!" class="fd-input w-full rounded-xl border border-border p-3.5" required>
        <x-ui.field-error field="discount_introduction" />
      </div>

      <div class="flex flex-col gap-2">
        <label for="coupon_code" class="fd-label">Coupon code (optional)</label>
        <input id="coupon_code" name="coupon_code" type="text" maxlength="15" value="{{ old('coupon_code', $lto['coupon_code'] ?? '') }}" placeholder="SAVE20" class="fd-input w-full rounded-xl border border-border p-3.5">
      </div>

      <label class="flex items-center gap-2">
        <input type="checkbox" id="expiration_time" name="expiration_time" value="1" class="size-4 rounded" @checked(old('expiration_time', $lto['expiration_time'] ?? false))>
        <span class="text-sm text-text-body">Set offer expiration time</span>
      </label>

      <div class="flex flex-col gap-2">
        <label for="time_variable" class="fd-label">Expiration (minutes)</label>
        <input id="time_variable" name="time_variable" type="number" min="1" max="10080" value="{{ old('time_variable', $lto['time_variable'] ?? 60) }}" class="fd-input w-full rounded-xl border border-border p-3.5">
      </div>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="fd-btn-sm rounded bg-green-500 px-4 py-3 text-primary-2">Next</button>
    </div>
  </form>
</x-templates.builder-layout>
