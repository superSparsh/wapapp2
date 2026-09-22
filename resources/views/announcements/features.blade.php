<x-layouts.app title="New Features - WapApp" active="announcements.features">
  <div class="flex flex-col gap-4 p-4">
    <div>
      <h1 class="fd-page-title text-2xl">New Features</h1>
      <p class="fd-page-note">Request activation or a demo for announced platform features.</p>
    </div>

    @if (session('status'))
      <div class="rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ session('error') }}</div>
    @endif

    @if ($announcements->isEmpty())
      <div class="rounded-lg border border-dashed border-border bg-elevated p-12 text-center">
        <p class="text-sm font-medium text-text-subtle">No active feature announcements right now.</p>
      </div>
    @else
      <div class="flex flex-col gap-4">
        @foreach ($announcements as $announcement)
          <article class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="mb-2 flex items-center gap-2">
                  <span class="rounded bg-green-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-green-600">New</span>
                  <h2 class="text-lg font-bold text-text-primary">{{ $announcement->title }}</h2>
                </div>
                <div class="prose prose-sm max-w-none text-text-subtle">
                  {!! nl2br(e($announcement->body)) !!}
                </div>
                @if ($announcement->ends_at)
                  <p class="mt-2 text-xs text-text-muted">Available until {{ $announcement->ends_at->format('d M Y') }}</p>
                @endif
              </div>
              <div class="shrink-0">
                @if ($announcement->already_requested)
                  <span class="inline-flex rounded border border-border bg-surface px-4 py-2.5 text-sm font-semibold text-text-muted">
                    Request submitted
                  </span>
                @else
                  <form method="POST" action="{{ route('announcements.features.request', $announcement) }}">
                    @csrf
                    <button
                      type="submit"
                      class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-2.5 text-sm font-semibold text-primary-2 hover:opacity-90"
                    >
                      Request Activation / Demo
                    </button>
                  </form>
                @endif
              </div>
            </div>
          </article>
        @endforeach
      </div>

      <div class="mt-2">
        {{ $announcements->links() }}
      </div>
    @endif
  </div>
</x-layouts.app>
