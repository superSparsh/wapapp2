@props(['open' => false, 'closeHref' => null])

<div
  id="modal-create-bot"
  data-modal="create-bot"
  @class([
    'fixed inset-0 z-50 items-center justify-center bg-black/60 p-4',
    'flex' => $open,
    'hidden' => ! $open,
  ])
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title-create-bot"
>
  <div class="flex max-h-[90vh] w-full max-w-[597px] flex-col gap-4 overflow-y-auto rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
    <div class="flex items-start justify-end gap-4">
      <div class="min-w-0 flex-1">
        <h2 id="modal-title-create-bot" class="text-2xl font-bold leading-[1.5] text-text-primary">
          Create New AI Bot
        </h2>
        <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Configure a new bot for your WhatsApp AI assistant.
        </p>
      </div>
      @if (! empty($closeHref))
        <a href="{{ $closeHref }}" aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </a>
      @else
        <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      @endif
    </div>

    <form action="{{ route('openai-key.bots.store') }}" method="POST" class="flex flex-col gap-4">
      @csrf
      <div class="w-full overflow-hidden rounded-xl border border-border-light bg-muted-surface p-4">
        <div class="flex flex-col gap-6">
          <div class="flex flex-col gap-2">
            <label for="bot_name" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Bot Name<span class="text-[red]">*</span>
            </label>
            <input
              id="bot_name"
              name="name"
              type="text"
              required
              value="{{ old('name') }}"
              placeholder="Enter Bot name"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>

          <div class="flex flex-col gap-2">
            <label for="bot_type" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Select Purpose / Type
            </label>
            <div class="relative">
              <select
                id="bot_type"
                name="type"
                class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="" @selected(! old('type'))>Select bot type</option>
                <option value="sales" @selected(old('type') === 'sales')>Sales</option>
                <option value="general" @selected(old('type') === 'general')>General</option>
                <option value="lead-gen" @selected(old('type') === 'lead-gen')>Lead Gen</option>
                <option value="support" @selected(old('type') === 'support')>Support</option>
                <option value="other" @selected(old('type') === 'other')>Other</option>
              </select>
              <img
                src="{{ asset('images/commerce/arrow-down.svg') }}"
                alt=""
                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                width="16"
                height="16"
              >
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label for="bot_provider" class="text-sm font-semibold leading-[1.4] text-text-primary">
              AI Provider
            </label>
            <div class="relative">
              <select
                id="bot_provider"
                name="provider"
                class="w-full appearance-none rounded-xl border border-border bg-elevated p-3.5 pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="openai" @selected(old('provider', 'openai') === 'openai')>OpenAI</option>
                <option value="gemini" @selected(old('provider') === 'gemini')>Google Gemini</option>
                <option value="azure" @selected(old('provider') === 'azure')>Azure OpenAI</option>
              </select>
              <img
                src="{{ asset('images/commerce/arrow-down.svg') }}"
                alt=""
                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                width="16"
                height="16"
              >
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label for="bot_chat_model" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Chat Model <span class="text-text-muted">(optional)</span>
            </label>
            <input
              id="bot_chat_model"
              name="chat_model"
              type="text"
              value="{{ old('chat_model') }}"
              placeholder="gpt-4o-mini"
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>

          <div class="flex flex-col gap-2">
            <label for="bot_system_prompt" class="text-sm font-semibold leading-[1.4] text-text-primary">
              System Prompt <span class="text-text-muted">(optional)</span>
            </label>
            <textarea
              id="bot_system_prompt"
              name="system_prompt"
              rows="4"
              placeholder="You are a helpful sales assistant..."
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >{{ old('system_prompt') }}</textarea>
          </div>

          <div class="flex flex-col gap-2">
            <label for="bot_business_info" class="text-sm font-semibold leading-[1.4] text-text-primary">
              Business Information <span class="text-text-muted">(optional)</span>
            </label>
            <textarea
              id="bot_business_info"
              name="business_information"
              rows="3"
              placeholder="Company name, products, services, pricing..."
              class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >{{ old('business_information') }}</textarea>
          </div>

          <label class="flex items-center gap-3">
            <input
              type="checkbox"
              name="is_default"
              value="1"
              @checked(old('is_default'))
              class="size-4 rounded border-border text-green-500 focus:ring-green-500"
            >
            <span class="text-sm font-medium leading-[1.4] text-text-body">
              Set as default bot
            </span>
          </label>
        </div>
      </div>

      <div class="flex w-full items-center justify-between">
        @if (! empty($closeHref))
          <a
            href="{{ $closeHref }}"
            class="fd-btn inline-flex items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </a>
        @else
          <button
            type="button"
            data-modal-close
            class="fd-btn inline-flex items-center justify-center rounded border border-solid border-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
          >
            Cancel
          </button>
        @endif
        <button
          type="submit"
          class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
        >
          Create Bot
        </button>
      </div>
    </form>
  </div>
</div>
