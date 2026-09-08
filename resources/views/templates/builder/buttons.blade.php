@php
  $savedButtons = old('buttons', $payload['buttons'] ?? []);
  $buttonMode = old('button_mode', $payload['button_mode'] ?? '');
  if (! old('button_mode') && empty($savedButtons)) {
      $buttonMode = '';
  }
  $flowOptions = collect($whatsappFlows ?? [])->map(fn ($flow) => [
      'id' => (string) ($flow['id'] ?? ''),
      'name' => (string) ($flow['name'] ?? ''),
      'meta_flow_id' => (string) ($flow['meta_flow_id'] ?? ''),
      'first_screen' => (string) ($flow['first_screen'] ?? ''),
      'is_ready' => (bool) ($flow['is_ready'] ?? false),
  ])->values();
  $buttonTextLimit = (int) config('templates.button_text_limit', 20);
  $maxButtons = (int) config('templates.max_buttons', 10);
  $maxUrlButtons = (int) config('templates.max_url_buttons', 2);
  $maxPhoneButtons = (int) config('templates.max_phone_buttons', 1);
@endphp

<x-templates.builder-layout active="buttons" :card="false" :template="$template" :payload="$payload" :preview-data="$previewData ?? null" :setup-complete="$setupComplete ?? true" :builder-steps="$builderSteps ?? null">
  <form method="post" action="{{ route('templates.builder.buttons.save', $template) }}" class="flex flex-col gap-4" id="template-buttons-form">
    @csrf

    <div
      id="template-buttons-root"
      data-saved-buttons='@json($savedButtons)'
      data-whatsapp-flows='@json($flowOptions)'
      data-button-text-limit="{{ $buttonTextLimit }}"
      data-max-buttons="{{ $maxButtons }}"
      data-max-url-buttons="{{ $maxUrlButtons }}"
      data-max-phone-buttons="{{ $maxPhoneButtons }}"
      class="flex flex-col gap-4"
    >
      <div class="flex w-full max-w-[320px] flex-col gap-2">
        <label for="button_mode" class="text-sm font-semibold leading-[1.5] text-text-body">Button Group<span class="text-[red]">*</span></label>
        <x-ui.select id="button_mode" name="button_mode" variant="default" class="w-full" required>
          <option value="" @selected($buttonMode === '') disabled>Select button group</option>
          <option value="none" @selected($buttonMode === 'none')>None</option>
          <option value="call_to_action" @selected($buttonMode === 'call_to_action')>Call To Action</option>
          <option value="quick_reply" @selected($buttonMode === 'quick_reply')>Quick Reply</option>
          <option value="whatsapp_flows" @selected($buttonMode === 'whatsapp_flows')>WhatsApp Flows</option>
          <option value="mixed" @selected($buttonMode === 'mixed')>Mixed (CTA + Quick Reply)</option>
          @if (($payload['meta']['category'] ?? '') === 'LIMITED_TIME_OFFER' || ($template->category ?? '') === 'LIMITED_TIME_OFFER')
            <option value="lto" @selected($buttonMode === 'lto')>Limited Time Offer (Copy Code)</option>
          @endif
        </x-ui.select>
        <p class="text-xs text-text-subtle" data-button-limit-summary>0 / {{ $maxButtons }} buttons (max {{ $maxUrlButtons }} URL, {{ $maxPhoneButtons }} phone)</p>
      </div>

      <div data-cta-actions class="hidden flex-wrap gap-2">
        <button type="button" id="add-cta-website" class="fd-btn-sm rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Website</button>
        <button type="button" id="add-cta-phone" class="fd-btn-sm rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Call</button>
        <button type="button" id="add-cta-unsubscribe" class="fd-btn-sm rounded border border-green-500 px-3 py-2 text-sm text-green-500">+ Unsubscribe</button>
      </div>

      {{-- Opt-out footer — disabled for now
      <div data-opt-out-wrapper class="hidden flex items-center gap-2">
        <input type="checkbox" id="is_opt_out" name="is_opt_out" value="1" class="size-4 rounded border-border text-green-500 focus:ring-green-500">
        <label for="is_opt_out" class="text-sm font-medium text-text-body">Add opt-out footer ("Not interested? Tap Stop promotions")</label>
      </div>
      --}}

      <div data-flow-section class="hidden flex-col gap-2">
        <p class="text-sm text-text-subtle">Select a flow you created in Flow Builder.</p>
      </div>

      <div id="button-validation-errors" class="hidden rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-600"></div>

      <div id="template-button-rows" class="flex flex-col gap-3"></div>

      <button
        type="button"
        id="add-template-button"
        class="fd-btn-sm hidden w-fit items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-green-500"
      >
        + Add Quick Reply
      </button>
    </div>

    <div class="flex w-full flex-col items-end justify-center">
      <button type="submit" class="fd-btn-sm inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-primary-2 transition-opacity hover:opacity-90">Next</button>
    </div>
  </form>
</x-templates.builder-layout>
