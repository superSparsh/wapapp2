<x-layouts.app title="{{ $flow->name }} - Stats" active="automation.chatbot">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <a href="{{ route('chatbot.index') }}" class="text-sm text-text-subtle hover:text-text-body">&larr; Back</a>
        </div>
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $flow->name }} &mdash; Statistics</h1>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Total Entered</p>
          <p class="mt-1 text-2xl font-bold leading-[1.5] text-text-primary">{{ $stats['total_entered'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Completed</p>
          <p class="mt-1 text-2xl font-bold leading-[1.5] text-green-600">{{ $stats['total_completed'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Dropped</p>
          <p class="mt-1 text-2xl font-bold leading-[1.5] text-yellow-600">{{ $stats['total_dropped'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <p class="text-xs font-medium leading-[1.5] text-text-muted">Errors</p>
          <p class="mt-1 text-2xl font-bold leading-[1.5] text-red-600">{{ $stats['total_errors'] ?? 0 }}</p>
        </div>
      </div>

      <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <h2 class="text-base font-semibold leading-[1.5] text-text-primary">Completion Rate</h2>
        @php
          $total = ($stats['total_entered'] ?? 0);
          $rate = $total > 0 ? round((($stats['total_completed'] ?? 0) / $total) * 100, 1) : 0;
        @endphp
        <div class="mt-3 flex items-center gap-4">
          <div class="h-4 flex-1 overflow-hidden rounded-full bg-muted-surface">
            <div class="h-full rounded-full bg-green-500" style="width: {{ $rate }}%"></div>
          </div>
          <span class="text-lg font-bold text-text-primary">{{ $rate }}%</span>
        </div>
      </div>

      @if (!empty($stats['top_nodes']))
        <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <h2 class="text-base font-semibold leading-[1.5] text-text-primary">Top Nodes by Activity</h2>
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-divider">
                  <th class="p-2 text-xs font-medium text-text-muted">Node</th>
                  <th class="p-2 text-xs font-medium text-text-muted">Type</th>
                  <th class="p-2 text-xs font-medium text-text-muted">Entries</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($stats['top_nodes'] as $node)
                  <tr class="border-t border-divider">
                    <td class="p-2 text-sm text-text-body">{{ $node['node_id'] }}</td>
                    <td class="p-2 text-sm text-text-subtle">{{ $node['node_type'] }}</td>
                    <td class="p-2 text-sm font-semibold text-text-primary">{{ $node['count'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
