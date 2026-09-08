<button
  type="button"
  @if ($item['modal']) data-open-modal="{{ $item['modal'] }}" @endif
  @if (! empty($item['requires_window'])) data-inbox-requires-window @endif
  @class([
    'fd-filter-label flex h-12 w-full items-center gap-3 rounded-lg border border-border bg-elevated p-2 text-left transition-colors hover:border-green-500 disabled:cursor-not-allowed disabled:opacity-50',
  ])
>
  <img
    src="{{ asset('images/inbox/menu/' . $item['image']) }}"
    alt=""
    class="size-8 shrink-0 rounded-lg object-cover"
    width="32"
    height="32"
  >
  {{ $item['label'] }}
</button>
