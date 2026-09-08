@props(['usage' => null])

@if (! empty($usage['limit']) && $usage['is_full'])
  <div class="rounded-lg border border-amber-500/30 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    Team member limit reached ({{ $usage['used'] }}/{{ $usage['limit'] }}).
    Upgrade your plan to add more members.
  </div>
@elseif (! empty($usage['limit']))
  <p class="text-sm text-text-subtle opacity-70">
    {{ $usage['used'] }} of {{ $usage['limit'] }} team members used
  </p>
@endif
