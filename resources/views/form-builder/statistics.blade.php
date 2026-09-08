<x-layouts.app title="{{ $form->name }} Statistics - WapApp" active="form-builder.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex flex-wrap items-center gap-3">
          <a href="{{ route('form-builder.index') }}" class="text-sm font-medium text-green-500 hover:underline">&larr; Back to forms</a>
        </div>
        <h1 class="fd-page-title text-2xl">{{ $form->name }}</h1>
        <p class="fd-page-note">
          Form statistics
          @if ($form->mailList)
            · List: {{ $form->mailList->name }}
          @endif
          @if ($form->template)
            · Template: {{ $form->template->name }}
          @endif
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold leading-[1.5] text-text-primary">Statistics</h2>
        <div class="flex flex-wrap items-center gap-2">
          <a
            href="{{ route('form-builder.statistics', $form) }}"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-surface"
          >
            <img src="{{ asset('images/form-builder/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </a>
          <a
            href="{{ $form->publicUrl() }}"
            target="_blank"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-text-body transition-colors hover:bg-surface"
          >
            Open public form
          </a>
          <a
            href="{{ route('form-builder.edit', $form) }}"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90"
          >
            Edit form
          </a>
        </div>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="rounded-lg bg-elevated p-3">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          @php
            $total = max(0, (int) ($stats['total'] ?? 0));
            $cards = [
              ['title' => 'Total Submissions', 'count' => $total, 'percent' => $total > 0 ? '100' : '0', 'color' => 'blue', 'icon' => 'task'],
              ['title' => 'Messages Sent', 'count' => (int) ($stats['sent'] ?? 0), 'percent' => $percent['sent'], 'color' => 'green', 'icon' => 'send'],
              ['title' => 'Delivered', 'count' => (int) ($stats['delivered'] ?? 0), 'percent' => $percent['delivered'], 'color' => 'emerald', 'icon' => 'send'],
              ['title' => 'Read', 'count' => (int) ($stats['read'] ?? 0), 'percent' => $percent['read'], 'color' => 'purple', 'icon' => 'tick-circle'],
              ['title' => 'Failed', 'count' => (int) ($stats['failed'] ?? 0), 'percent' => $percent['failed'], 'color' => 'red', 'icon' => 'warning'],
            ];
          @endphp

          @foreach ($cards as $card)
            <x-ui.campaign-metric-card
              :title="$card['title']"
              :count="number_format($card['count'])"
              :total="number_format($total)"
              :percent="$card['percent']"
              :color="$card['color']"
              :icon="$card['icon']"
            />
          @endforeach
        </div>
      </div>
    </section>

    <section class="bg-surface p-4 pt-0">
      <div class="rounded-lg bg-elevated p-4">
        <h3 class="mb-3 text-lg font-bold text-text-primary">Recent submissions</h3>
        @if ($recentSubmissions->isEmpty())
          <p class="py-8 text-center text-sm text-text-muted">No submissions yet. Share the public form link to start collecting leads.</p>
        @else
          <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
              <thead>
                <tr class="border-b border-border text-xs font-semibold uppercase tracking-wide text-text-muted">
                  <th class="px-3 py-2">Phone</th>
                  <th class="px-3 py-2">Status</th>
                  <th class="px-3 py-2">Submitted</th>
                  <th class="px-3 py-2">Sent</th>
                  <th class="px-3 py-2">Delivered</th>
                  <th class="px-3 py-2">Read</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($recentSubmissions as $submission)
                  @php
                    $status = (string) ($submission->message_status ?? 'pending');
                    $statusClass = match ($status) {
                      'sent' => 'bg-green-50 text-green-700',
                      'delivered' => 'bg-teal-50 text-teal-700',
                      'read' => 'bg-blue-50 text-blue-700',
                      'failed' => 'bg-red-50 text-red-600',
                      default => 'bg-muted-surface text-text-muted',
                    };
                  @endphp
                  <tr class="border-b border-border/60">
                    <td class="px-3 py-2.5 font-medium text-text-body">{{ $submission->phone ?: '—' }}</td>
                    <td class="px-3 py-2.5">
                      <span class="inline-flex rounded px-2 py-1 text-[10px] font-medium {{ $statusClass }}">{{ ucfirst($status) }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-text-muted">{{ $submission->created_at?->format('d M Y h:i A') ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-text-muted">{{ $submission->sent_at?->format('d M Y h:i A') ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-text-muted">{{ $submission->delivered_at?->format('d M Y h:i A') ?? '—' }}</td>
                    <td class="px-3 py-2.5 text-text-muted">{{ $submission->read_at?->format('d M Y h:i A') ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
