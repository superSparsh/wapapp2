@props([
    'actions' => [],
])

@php
  $rows = [];
  foreach (is_array($actions) ? $actions : [] as $action) {
      if (is_string($action)) {
          $text = trim($action);
          $type = 'quick_reply';
      } elseif (is_array($action)) {
          $text = trim((string) ($action['text'] ?? $action['title'] ?? ''));
          $type = strtolower(trim((string) ($action['type'] ?? 'quick_reply')));
      } else {
          continue;
      }
      if ($text === '') {
          continue;
      }
      $icon = match ($type) {
          'phone', 'phone_number', 'call' => 'call.svg',
          'cta_url', 'url', 'cta', 'call_to_action' => 'export.svg',
          'flow' => 'flow.svg',
          'list' => 'quick-reply.svg',
          'copy_code' => 'quick-reply.svg',
          default => 'quick-reply.svg',
      };
      $folder = in_array($icon, ['call.svg', 'export.svg'], true) ? 'inbox/modals' : 'templates';
      $rows[] = ['text' => $text, 'icon' => $folder.'/'.$icon];
  }
@endphp

@if ($rows !== [])
  <div {{ $attributes->class('mt-1.5 w-full border-t border-black/10 pt-1') }}>
    @foreach ($rows as $row)
      <div class="flex w-full items-center justify-center gap-2 py-1.5">
        <img src="{{ asset('images/'.$row['icon']) }}" alt="" class="size-3.5 shrink-0 opacity-90" width="14" height="14">
        <span class="text-sm font-medium leading-none text-link-green">{{ $row['text'] }}</span>
      </div>
    @endforeach
  </div>
@endif
