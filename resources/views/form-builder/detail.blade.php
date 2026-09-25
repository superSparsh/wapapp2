<x-layouts.app title="{{ $form->name }} Submissions - WapApp" active="form-builder.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 bg-surface px-4 pb-4 pt-4">
      <div class="flex w-full flex-col gap-2">
        <div class="flex flex-wrap items-center gap-3">
          <a href="{{ route('form-builder.statistics', $form) }}" class="text-sm font-medium text-green-500 hover:underline">&larr; Back to statistics</a>
        </div>
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">
          {{ $form->name }}
        </h2>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Detailed submission log for this form.
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h3 class="text-xl font-bold leading-[1.5] text-text-primary">Submissions</h3>
          <span class="rounded bg-elevated px-2 py-1 text-xs font-medium text-text-muted">
            {{ number_format($submissions->total()) }} total
          </span>
        </div>
        <div class="flex items-center gap-3">
          <form method="GET" action="{{ route('form-builder.statistics.detail', $form) }}" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="flex min-w-[160px] items-center gap-2.5 rounded-lg bg-elevated p-3 text-sm">
              <option value="">All Statuses</option>
              @foreach (['sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'pending' => 'Pending'] as $value => $label)
                <option value="{{ $value }}" @selected($currentStatus === $value)>
                  {{ $label }}
                </option>
              @endforeach
            </select>
          </form>
          <a
            href="{{ route('form-builder.statistics.export', array_filter(['form' => $form, 'status' => $currentStatus ?: null])) }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-100"
          >
            <img src="{{ asset('images/automation/export-csv.svg') }}" alt="" class="size-4" width="16" height="16">
            Export to CSV
          </a>
        </div>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Contact Phone</th>
                <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Contact Name</th>
                <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                <th class="min-w-[220px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Reason</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Submitted At</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Sent At</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Delivered At</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Failed At</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($submissions as $index => $submission)
                @php
                  $serial = ($submissions->currentPage() - 1) * $submissions->perPage() + $index + 1;
                  $statusValue = $submission->displayStatus();
                  $statusColor = match ($statusValue) {
                      'delivered' => 'text-blue-600 bg-[rgba(59,130,246,0.1)]',
                      'read' => 'text-green-600 bg-[rgba(16,185,129,0.1)]',
                      'failed' => 'text-red-600 bg-[rgba(239,68,68,0.1)]',
                      'sent' => 'text-gray-600 bg-[rgba(107,114,128,0.1)]',
                      default => 'text-text-muted bg-[rgba(0,0,0,0.05)]',
                  };
                  $reason = trim((string) ($submission->failed_reason ?? ''));
                @endphp
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $serial }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $submission->phone ?: 'N/A' }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $submission->contact?->name ?? 'N/A' }}</td>
                  <td class="p-2 text-center">
                    <span class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap {{ $statusColor }}">
                      {{ ucfirst($statusValue) }}
                    </span>
                  </td>
                  <td class="min-w-[220px] max-w-[360px] p-2 text-[13px] font-normal leading-[1.5] break-words text-text-body" title="{{ $reason !== '' ? $reason : '—' }}">
                    {{ $statusValue === 'failed' && $reason !== '' ? $reason : '—' }}
                  </td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $submission->created_at?->format('d M Y h:i A') ?? '—' }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $submission->sent_at?->format('d M Y h:i A') ?? '—' }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $submission->delivered_at?->format('d M Y h:i A') ?? '—' }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $submission->failed_at?->format('d M Y h:i A') ?? '—' }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="9" class="p-8 text-center text-sm text-text-body">No submissions recorded yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($submissions->hasPages())
          <x-ui.table-pagination :paginator="$submissions" />
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
