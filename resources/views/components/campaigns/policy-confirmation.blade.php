@props([
    'checked' => false,
])

<div class="flex flex-col gap-3 rounded-xl border border-border bg-elevated p-4" data-validate-field>
  <p class="text-sm leading-[1.5] text-text-muted">
    Before sending this campaign, make sure everyone in your selected list has agreed to receive WhatsApp messages from you.
    Sending messages to people who haven't opted in (such as purchased or cold contact lists) can reduce your account quality
    and may result in temporary or permanent restrictions by Meta.
  </p>
  <p class="text-sm leading-[1.5] text-text-muted">
    Tekpro / WAPAPP cannot be held responsible for any action taken against your WhatsApp Business account due to policy violations.
  </p>
  <label class="flex cursor-pointer items-start gap-3">
    <input
      type="checkbox"
      name="policy_confirmed"
      id="policy_confirmed"
      value="1"
      class="mt-0.5 size-4 shrink-0 rounded border-border text-green-500 focus:ring-green-500"
      @checked($checked)
      required
    >
    <span class="text-sm font-semibold leading-[1.5] text-text-primary">
      I confirm that this campaign complies with WhatsApp's policies and that I accept full responsibility for it.
    </span>
  </label>
  @error('policy_confirmed')
    <p class="text-xs text-red-500">{{ $message }}</p>
  @enderror
  <p id="policy-confirm-error" class="hidden text-xs text-red-500">Please confirm that this campaign complies with WhatsApp's policies.</p>
</div>
