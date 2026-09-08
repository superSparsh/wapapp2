@php
  $stats = $stats ?? [];
  $totalTokens = (int) ($stats['total_tokens'] ?? 0);
  $totalCost = (float) ($stats['total_cost_usd'] ?? 0);
  $totalRequests = (int) ($stats['total_requests'] ?? 0);
  $byModel = $stats['by_model'] ?? [];
@endphp

<x-layouts.app title="{{ $bot->name }} Usage - WapApp" active="ai-bots">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex items-center gap-2"><a href="{{ route('ai-bots.show', $bot) }}" class="text-text-subtle hover:text-text-body">&larr; Back to {{ $bot->name }}</a></div>
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $bot->name }} — Usage</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Token consumption and cost tracking for this AI bot.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl bg-elevated p-5">
          <p class="text-xs text-text-muted">Total Requests</p>
          <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format($totalRequests) }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5">
          <p class="text-xs text-text-muted">Total Tokens</p>
          <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format($totalTokens) }}</p>
        </div>
        <div class="rounded-xl bg-elevated p-5">
          <p class="text-xs text-text-muted">Estimated Cost (USD)</p>
          <p class="mt-1 text-2xl font-bold text-green-500">${{ number_format($totalCost, 4) }}</p>
        </div>
      </div>

      @if (! empty($byModel))
        <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <div class="p-4 text-base font-semibold text-text-primary">Usage by Model</div>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-t border-divider bg-elevated">
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Model</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Requests</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Prompt Tokens</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Completion Tokens</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Total Tokens</th>
                  <th class="p-3 text-[13px] font-medium leading-[1.5] text-text-body">Cost (USD)</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($byModel as $row)
                  <tr class="border-t border-divider bg-elevated">
                    <td class="p-3 text-[13px] font-semibold text-text-primary">{{ $row['model'] }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ number_format((int) ($row['requests'] ?? 0)) }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ number_format((int) ($row['prompt_tokens'] ?? 0)) }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ number_format((int) ($row['completion_tokens'] ?? 0)) }}</td>
                    <td class="p-3 text-[13px] text-text-body">{{ number_format((int) ($row['total_tokens'] ?? 0)) }}</td>
                    <td class="p-3 text-[13px] text-green-500">${{ number_format((float) ($row['cost_usd'] ?? 0), 4) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @else
        <div class="rounded-xl bg-elevated p-8 text-center text-sm text-text-muted">No usage data recorded yet.</div>
      @endif
    </div>
  </div>
</x-layouts.app>
