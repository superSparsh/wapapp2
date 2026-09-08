<x-layouts.app title="Stats - {{ $flow->name }} - WapApp" active="automation.whatsapp-flows">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <a href="{{ route('whatsapp-flows.show', $flow) }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back to flow</a>
        </div>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }} — Stats</h1>
      </div>

      {{-- Stat Cards --}}
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Total Submissions</p>
          <p class="mt-1 text-3xl font-bold text-text-primary">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Processed</p>
          <p class="mt-1 text-3xl font-bold text-[green]">{{ $stats['processed'] }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Failed</p>
          <p class="mt-1 text-3xl font-bold text-red-500">{{ $stats['failed'] }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium text-text-muted">Completion Rate</p>
          <p class="mt-1 text-3xl font-bold text-text-primary">{{ $stats['rate'] }}%</p>
        </div>
      </div>

      {{-- Progress Bar --}}
      @if ($stats['total'] > 0)
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="mb-3 text-sm font-semibold text-text-body">Processing Overview</p>
          <div class="flex h-4 w-full overflow-hidden rounded-full bg-surface">
            @if ($stats['processed'] > 0)
              <div class="bg-green-500" style="width: {{ ($stats['processed'] / $stats['total']) * 100 }}%"></div>
            @endif
            @if ($stats['failed'] > 0)
              <div class="bg-red-400" style="width: {{ ($stats['failed'] / $stats['total']) * 100 }}%"></div>
            @endif
          </div>
          <div class="mt-2 flex gap-4 text-xs text-text-muted">
            <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-green-500"></span> Processed ({{ $stats['processed'] }})</span>
            <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-red-400"></span> Failed ({{ $stats['failed'] }})</span>
            <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-surface"></span> Pending ({{ $stats['total'] - $stats['processed'] - $stats['failed'] }})</span>
          </div>
        </div>
      @endif

      {{-- Recent Submissions Table --}}
      <div class="rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="flex items-center justify-between border-b border-divider p-4">
          <h2 class="text-base font-semibold text-text-primary">Recent Submissions</h2>
          <span class="text-xs text-text-muted">Showing latest {{ count($recentSubmissions) }} records</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[600px] text-left">
            <thead>
              <tr class="bg-elevated border-b border-divider">
                <th class="p-3 text-[13px] font-semibold text-text-body">#</th>
                <th class="p-3 text-[13px] font-semibold text-text-body">Phone</th>
                <th class="p-3 text-[13px] font-semibold text-text-body">Status</th>
                @if (!empty($submissionKeys))
                  @foreach ($submissionKeys as $key)
                    <th class="p-3 text-[13px] font-semibold text-text-body whitespace-nowrap">
                      {{ $fieldMap[$key] ?? ucwords(str_replace('_', ' ', preg_replace('/_\d+$/', '', $key))) }}
                    </th>
                  @endforeach
                @else
                  <th class="p-3 text-[13px] font-semibold text-text-body">Form Data</th>
                @endif
                <th class="p-3 text-[13px] font-semibold text-text-body whitespace-nowrap">Processed At</th>
                <th class="p-3 text-[13px] font-semibold text-text-body whitespace-nowrap">Received</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($recentSubmissions as $i => $sub)
                <tr class="border-t border-divider hover:bg-surface/50 transition-colors">
                  <td class="p-3 text-[13px] text-text-body">{{ $i + 1 }}</td>
                  <td class="p-3 text-[13px] font-medium text-text-body">
                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">
                      {{ $sub->contact_phone }}
                    </span>
                  </td>
                  <td class="p-3">
                    @if ($sub->status === 'processed')
                      <span class="rounded bg-[rgba(0,128,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-[green]">Processed</span>
                    @elseif ($sub->status === 'failed')
                      <span class="rounded bg-[rgba(255,0,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-red-500">Failed</span>
                    @else
                      <span class="rounded bg-[rgba(0,0,0,0.1)] px-2 py-0.5 text-[10px] font-medium text-text-muted">Received</span>
                    @endif
                  </td>
                  @if (!empty($submissionKeys))
                    @foreach ($submissionKeys as $key)
                      @php
                        $val = $sub->form_data[$key] ?? null;
                      @endphp
                      <td class="p-3 text-[13px] text-text-body whitespace-nowrap">
                        @if (is_array($val))
                          <span class="inline-flex flex-wrap gap-1">
                            @foreach ($val as $v)
                              <span class="rounded bg-elevated border border-divider px-1.5 py-0.5 text-[11px] text-text-subtle">{{ $v }}</span>
                            @endforeach
                          </span>
                        @elseif ($val !== null && $val !== '')
                          <span class="text-text-primary font-medium">{{ (string) $val }}</span>
                        @else
                          <span class="text-text-muted">—</span>
                        @endif
                      </td>
                    @endforeach
                  @else
                    <td class="p-3 text-[12px] text-text-subtle">
                      <span class="block max-w-[300px] truncate font-mono text-[11px]">{{ json_encode($sub->form_data) }}</span>
                    </td>
                  @endif
                  <td class="p-3 text-[13px] text-text-body whitespace-nowrap">{{ $sub->processed_at?->format('M d, H:i') ?? '—' }}</td>
                  <td class="p-3 text-[13px] text-text-body whitespace-nowrap">{{ $sub->created_at->format('M d, H:i') }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider">
                  <td colspan="{{ 5 + count($submissionKeys ?? []) }}" class="p-8 text-center text-sm text-text-muted">No submissions yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
