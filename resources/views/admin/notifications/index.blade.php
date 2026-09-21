<x-admin.layout title="Notifications - Admin" active="admin.notifications.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Notifications</h1>
      <p class="text-sm text-text-subtle">
        {{ $unreadCount }} unread — new customers, platform errors, renew &amp; recharge requests.
      </p>
    </div>
    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
      @csrf
      <button
        type="submit"
        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface disabled:opacity-40"
        @disabled($unreadCount === 0)
      >Mark all read</button>
    </form>
  </div>

  <section class="space-y-2 p-4 pt-0">
    @forelse ($notifications as $notification)
      @php
        $href = $notification->link ?: null;
        $typeLabel = $notification->type?->label() ?? 'Update';
      @endphp
      <article class="rounded-xl border border-border bg-elevated p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-sm font-bold text-text-primary">{{ $notification->title }}</h2>
              <span class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-text-subtle">{{ $typeLabel }}</span>
            </div>
            @if ($notification->body)
              <p class="mt-1 text-sm text-text-subtle">{{ $notification->body }}</p>
            @endif
            <p class="mt-2 text-xs text-text-subtle">{{ $notification->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</p>
          </div>
          <div class="flex items-center gap-2">
            @if ($href)
              <a href="{{ route('admin.notifications.read', $notification) }}?redirect={{ urlencode(parse_url($href, PHP_URL_PATH) ?: '/') }}" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white">Open</a>
            @else
              <form method="POST" action="{{ route('admin.notifications.read', $notification) }}">
                @csrf
                <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Mark read</button>
              </form>
            @endif
          </div>
        </div>
      </article>
    @empty
      <div class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-text-subtle">
        You’re all caught up — no unread notifications.
      </div>
    @endforelse
  </section>
</x-admin.layout>
