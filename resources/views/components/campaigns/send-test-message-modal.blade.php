<div
  id="modal-send-test-message"
  data-modal="send-test-message"
  data-test-message-url="{{ route('campaigns.create.test-message') }}"
  class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4"
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-send-test-message"
>
  <div class="flex max-h-[90vh] w-full max-w-[681px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-send-test-message" class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $wizardData['name'] ?? 'Campaign' }}</h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Send a test WhatsApp message</p>
      </div>
      <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
        <img src="{{ asset('images/campaigns/create/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
      </button>
    </div>

    <form id="campaign-test-message-form" class="flex flex-col gap-4" data-campaign-test-message-form>
      @csrf
      <div class="rounded-xl border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-2" data-validate-field>
          <label for="test_phone_number" class="text-sm font-semibold leading-[1.4] text-text-primary">
            Enter a phone number for testing whatsapp message <x-form.required />
          </label>
          <input
            id="test_phone_number"
            name="phone"
            type="text"
            required
            maxlength="32"
            placeholder="919999999999"
            class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted outline-none focus:border-green-500"
          >
        </div>
      </div>

      @php
        $testVars = is_array($wizardData['template_variables'] ?? null) ? $wizardData['template_variables'] : [];
      @endphp
      @if (count($testVars) > 0)
        <div class="flex flex-col gap-3 rounded-xl border border-border-light bg-muted-surface p-4">
          <p class="text-sm font-semibold text-text-primary">Template variables</p>
          @foreach ($testVars as $varName => $varValue)
            <div class="flex flex-col gap-2" data-validate-field>
              <label class="text-sm font-medium text-text-body" for="test-var-{{ $varName }}">{{ $varName }} <x-form.required /></label>
              <input
                id="test-var-{{ $varName }}"
                type="text"
                name="template_variables[{{ $varName }}]"
                value="{{ $varValue }}"
                required
                maxlength="60"
                class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium text-text-muted outline-none focus:border-green-500"
              >
            </div>
          @endforeach
        </div>
      @endif

      <div class="flex items-start gap-3 rounded-xl bg-stat-orange/15 p-3.5">
        <img src="{{ asset('images/campaigns/create/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
        <div class="flex min-w-0 flex-1 flex-col gap-2.5 text-sm">
          <p class="font-bold leading-[1.4] text-text-body">Important:</p>
          <p class="font-normal leading-[1.4] text-text-muted">
            Please include the country code without the + sign. (e.g. 919999999999 for India)
          </p>
        </div>
      </div>

      <p data-test-message-status class="hidden text-sm font-medium" role="status" aria-live="polite"></p>

      <div class="flex items-center justify-end">
        <button
          type="submit"
          data-test-message-submit
          class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <img src="{{ asset('images/campaigns/create/send.svg') }}" alt="" class="size-5" width="20" height="20" data-test-message-send-icon>
          <svg data-test-message-spinner class="hidden size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
          <span data-test-message-submit-label>Send</span>
        </button>
      </div>
    </form>
  </div>
</div>
