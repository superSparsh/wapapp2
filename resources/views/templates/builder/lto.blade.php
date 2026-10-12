@php
  $lto = $payload['lto'] ?? [];
  $expirationOn = old('expiration_time', $lto['expiration_time'] ?? false);
@endphp

<x-templates.builder-layout active="lto" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null" identity-form-id="builder-lto-form">
  <form id="builder-lto-form" method="post" action="{{ route('templates.builder.lto.save', $template) }}" class="flex flex-col gap-4" data-validate-form>
    @csrf

    <div class="rounded-lg bg-elevated p-4 flex flex-col gap-6">
      <div>
        <h2 class="text-base font-semibold text-text-body">Limited-time offer settings</h2>
        <p class="mt-1 text-sm text-text-subtle">Configure the offer strip shown above your message body. Add a copy-code button on the Buttons step.</p>
      </div>

      <div class="flex flex-col gap-2">
        <label for="discount_introduction" class="fd-label">Discount introduction<span class="text-[red]">*</span></label>
        <input id="discount_introduction" name="discount_introduction" type="text" maxlength="16" value="{{ old('discount_introduction', $lto['discount_introduction'] ?? 'Get 20% off') }}" placeholder="Get 20% off" class="fd-input w-full max-w-md rounded-xl border border-border p-3.5" required>
        <p class="text-xs text-text-subtle">Max 16 characters (WhatsApp LTO limit).</p>
        <x-ui.field-error field="discount_introduction" />
      </div>

      <div class="flex flex-col gap-2">
        <label for="coupon_code" class="fd-label">Coupon code (optional)</label>
        <input id="coupon_code" name="coupon_code" type="text" maxlength="15" value="{{ old('coupon_code', $lto['coupon_code'] ?? '') }}" placeholder="SAVE20" class="fd-input w-full max-w-md rounded-xl border border-border p-3.5">
        <p class="text-xs text-text-subtle">Used for the copy-code button if you do not enter a code on the Buttons step.</p>
      </div>

      <div class="flex flex-col gap-3 border-t border-border pt-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
          <label class="flex items-center gap-2">
            <input type="checkbox" id="expiration_time" name="expiration_time" value="1" class="size-4 rounded" @checked($expirationOn)>
            <span class="text-sm text-text-body">Offer expiration</span>
          </label>
          <div data-lto-expiration-variable @class(['flex flex-col gap-1', 'hidden' => ! $expirationOn])>
            <input type="text" readonly value="$(offerExpirationTimeMs)" class="fd-input w-full max-w-xs rounded-xl border border-border bg-muted-surface p-3.5 text-sm text-text-subtle">
            <span class="text-xs text-text-subtle">Shown at send time in the offer strip (legacy parity).</span>
          </div>
        </div>

        <div data-lto-expiration-fields @class(['flex flex-col gap-2 sm:flex-row sm:items-center', 'hidden' => ! $expirationOn])>
          <label for="time_variable" class="fd-label shrink-0">Template expiration (minutes)</label>
          <input id="time_variable" name="time_variable" type="number" min="1" max="10080" value="{{ old('time_variable', $lto['time_variable'] ?? 60) }}" class="fd-input w-full max-w-[140px] rounded-xl border border-border p-3.5">
        </div>
      </div>
    </div>

    <x-templates.builder-actions :back-url="$previousStepUrl ?? route('templates.index')" />
  </form>
</x-templates.builder-layout>
