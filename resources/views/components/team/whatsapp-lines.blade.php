@props(['whatsappLines' => [], 'assignedLineIds' => [], 'inputName' => 'whatsapp_line_ids'])

@if ($whatsappLines !== [])
  <div class="flex flex-col gap-3">
    <div class="flex flex-col gap-1">
      <h2 class="text-base font-semibold text-text-primary">WhatsApp lines</h2>
      <p class="text-sm text-text-subtle opacity-70">Choose which numbers this member can access in inbox.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      @foreach ($whatsappLines as $line)
        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-border bg-surface p-3.5 transition-colors hover:border-green-500/40 has-[:checked]:border-green-500 has-[:checked]:bg-green-500/5">
          <input
            type="checkbox"
            name="{{ $inputName }}[]"
            value="{{ $line->id }}"
            @checked(in_array($line->id, old($inputName, $assignedLineIds), true))
            class="size-4 shrink-0 rounded border-border text-green-500 focus:ring-green-500"
          >
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-text-primary">{{ $line->display_name ?: 'WhatsApp line' }}</span>
            <span class="block truncate text-xs text-text-subtle opacity-70">{{ $line->phone }}</span>
          </span>
        </label>
      @endforeach
    </div>
  </div>
@endif
