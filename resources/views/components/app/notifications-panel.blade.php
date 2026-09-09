<div
  id="notifications-panel"
  class="absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[360px] overflow-hidden rounded-2xl border border-border-light bg-elevated shadow-[0px_8px_24px_rgba(0,0,0,0.12)]"
  role="menu"
>
  <div class="flex items-center justify-between gap-3 border-b border-divider px-4 py-3">
    <h2 class="text-base font-semibold text-text-primary">Notifications</h2>
    <div class="flex items-center gap-2">
      @if (($notificationCount ?? 0) > 0)
        <span data-notification-count class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-primary-2">{{ $notificationCount }} new</span>
      @endif
      <button
        type="button"
        data-mark-all-notifications-read
        class="text-xs font-semibold text-primary-2 hover:underline disabled:cursor-not-allowed disabled:opacity-40"
        @disabled(($notificationCount ?? 0) === 0)
      >
        Mark all read
      </button>
    </div>
  </div>

  <div class="max-h-[360px] overflow-y-auto" data-notifications-list>
    @forelse ($notifications ?? [] as $notification)
      <div class="border-b border-divider px-4 py-3 last:border-b-0">
        <p class="text-sm font-medium text-text-primary">{{ \App\Domains\Account\Services\ActivityLogService::labelFor((string) $notification->action, $notification->description) }}</p>
        <p class="mt-1 text-xs text-text-muted">{{ $notification->created_at?->diffForHumans() }}</p>
      </div>
    @empty
      <div class="px-4 py-8 text-center text-sm text-text-muted" data-notifications-empty>No new notifications.</div>
    @endforelse
  </div>

  <div class="border-t border-divider px-4 py-3">
    <a href="{{ route('profile.activity-logs') }}" class="text-sm font-semibold text-primary-2 underline">View all activity</a>
  </div>
</div>
