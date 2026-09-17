<x-layouts.app title="Create Chatbot - WapApp" active="automation.chatbot">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Create Chatbot Flow</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Give your chatbot a name. If you have one WhatsApp number it is used automatically; if you have multiple, choose which number this bot should reply on.
        </p>
      </div>

      @if ($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
          <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form action="{{ route('chatbot.store') }}" method="POST" data-validate-form class="max-w-[600px] rounded-xl bg-elevated p-6 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        @csrf

        <div class="flex flex-col gap-5">
          <div class="flex flex-col gap-1.5">
            <label for="name" class="text-sm font-semibold leading-[1.5] text-text-body">Chatbot Name <span class="text-red-500">*</span></label>
            <input
              type="text"
              id="name"
              name="name"
              value="{{ old('name') }}"
              placeholder="e.g. Customer Support Bot"
              required
              class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
            >
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="whatsapp_line_id" class="text-sm font-semibold leading-[1.5] text-text-body">
              WhatsApp Number
              @if ($showWhatsappLinePicker ?? false)
                <span class="text-red-500">*</span>
              @endif
            </label>
            @if ($showWhatsappLinePicker ?? false)
              <select
                id="whatsapp_line_id"
                name="whatsapp_line_id"
                required
                class="w-full rounded-lg border border-divider bg-surface px-4 py-3 text-sm font-medium leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              >
                <option value="">-- Choose WhatsApp number --</option>
                @foreach ($whatsappLines as $line)
                  <option value="{{ $line->uuid }}" @selected((string) old('whatsapp_line_id') === (string) $line->uuid)>
                    {{ $line->display_name ?: $line->phone }}
                  </option>
                @endforeach
              </select>
              <p class="text-xs text-text-muted">This chatbot will only reply on the selected WhatsApp number.</p>
            @elseif (($whatsappLines ?? collect())->isNotEmpty())
              <input type="hidden" name="whatsapp_line_id" value="{{ $whatsappLines->first()->uuid }}">
              <div class="rounded-lg border border-divider bg-surface px-4 py-3 text-sm font-medium text-text-body">
                {{ $whatsappLines->first()->display_name ?: $whatsappLines->first()->phone }}
              </div>
              <p class="text-xs text-text-muted">Only one WhatsApp number is connected — this chatbot will use it automatically.</p>
            @else
              <p class="text-xs text-text-muted">No WhatsApp number connected yet. Add a number first, then create the chatbot.</p>
            @endif
          </div>

          <div class="flex items-center gap-3 pt-2">
            <a
              href="{{ route('chatbot.index') }}"
              class="inline-flex items-center justify-center rounded-lg border border-divider bg-surface px-5 py-3 text-sm font-semibold leading-[1.5] text-text-body transition-colors hover:bg-muted-surface"
            >
              Cancel
            </a>
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-500 px-5 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
            >
              <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-5" width="20" height="20">
              Create &amp; Open Builder
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</x-layouts.app>
