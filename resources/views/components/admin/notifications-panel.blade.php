<div
  id="admin-notifications-panel"
  class="absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[380px] overflow-hidden rounded-2xl border border-border bg-elevated shadow-[0px_8px_24px_rgba(0,0,0,0.12)]"
  role="menu"
  data-admin-notifications-panel
>
  <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
    <h2 class="text-base font-semibold text-text-primary">Notifications</h2>
    <div class="flex items-center gap-2">
      @if (($adminNotificationCount ?? 0) > 0)
        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">{{ $adminNotificationCount }} new</span>
      @endif
      <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" data-admin-mark-all-read>
        @csrf
        <button
          type="submit"
          class="text-xs font-semibold text-green-700 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
          @disabled(($adminNotificationCount ?? 0) === 0)
        >
          Mark all read
        </button>
      </form>
    </div>
  </div>

  <div class="max-h-[400px] overflow-y-auto">
    @forelse ($adminNotifications ?? [] as $notification)
      @php
        $typeLabel = $notification->type?->label() ?? 'Update';
        $linkPath = is_string($notification->link) ? $notification->link : '';
        if ($linkPath !== '' && str_contains($linkPath, '://')) {
          $parts = parse_url($linkPath);
          $linkPath = ($parts['path'] ?? '').(! empty($parts['query']) ? '?'.$parts['query'] : '');
        }
        if ($linkPath === '' || ! str_starts_with($linkPath, '/admin')) {
          $linkPath = route('admin.notifications.index', absolute: false);
        }
      @endphp
      <a
        href="{{ route('admin.notifications.read', $notification->id) }}?redirect={{ urlencode($linkPath) }}"
        class="block border-b border-border px-4 py-3 transition last:border-b-0 hover:bg-surface"
      >
        <div class="flex items-start justify-between gap-2">
          <p class="text-sm font-semibold text-text-primary">{{ $notification->title }}</p>
          <span class="shrink-0 rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-text-subtle">{{ $typeLabel }}</span>
        </div>
        @if ($notification->body)
          <p class="mt-1 line-clamp-2 text-xs text-text-subtle">{{ $notification->body }}</p>
        @endif
        <p class="mt-1 text-[11px] text-text-subtle">{{ $notification->created_at?->diffForHumans() }}</p>
      </a>
    @empty
      <div class="px-4 py-8 text-center text-sm text-text-subtle">No new notifications.</div>
    @endforelse
  </div>

  <div class="border-t border-border px-4 py-3">
    <a href="{{ route('admin.notifications.index') }}" class="text-sm font-semibold text-green-700 underline">View all notifications</a>
  </div>
</div>
