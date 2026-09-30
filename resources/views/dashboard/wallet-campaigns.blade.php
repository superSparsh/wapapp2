<x-layouts.app title="Wallet by Campaign - WapApp" active="dashboard">
  <div class="flex flex-col">
    <div class="flex flex-col gap-4 p-4">
      <x-dashboard.page-header
        title="Wallet by Campaign"
        subtitle="Open a campaign to see recipient-level wallet charges and export them."
      />

      <div class="flex flex-wrap items-center gap-3">
        <a
          href="{{ route('dashboard.wallet') }}"
          class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-green-500 hover:bg-surface"
        >
          All transactions
        </a>
      </div>
    </div>

    <section class="bg-surface px-4 pb-4">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head min-w-[280px] p-2">Campaign</th>
                <th class="fd-table-head min-w-[160px] p-2">Template</th>
                <th class="fd-table-head w-[120px] p-2">Charges</th>
                <th class="fd-table-head w-[140px] p-2">Total Debit</th>
                <th class="fd-table-head w-[180px] p-2">Last Charge</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($summaries as $index => $row)
                @php
                  $campaign = $row->campaign;
                  $name = $campaign?->name ?? ('Campaign #'.$row->campaign_id);
                  $href = $campaign
                    ? route('dashboard.wallet.campaign', $campaign)
                    : null;
                @endphp
                <tr
                  @class([
                    'border-t border-divider bg-elevated transition',
                    'cursor-pointer hover:bg-surface' => $href !== null,
                  ])
                  @if ($href)
                    onclick="window.location.href={{ json_encode($href) }}"
                    role="link"
                    tabindex="0"
                    onkeydown="if(event.key==='Enter'){window.location.href={{ json_encode($href) }}}"
                  @endif
                >
                  <td class="fd-table-cell p-2 pl-4">{{ str_pad((string) ($summaries->firstItem() + $index), 2, '0', STR_PAD_LEFT) }}</td>
                  <td class="p-2">
                    <p class="fd-table-name text-text-subtle">{{ $name }}</p>
                    @if ($campaign?->audience?->name)
                      <p class="mt-0.5 text-xs text-text-muted">{{ $campaign->audience->name }}</p>
                    @endif
                  </td>
                  <td class="fd-table-cell p-2">
                    {{ $campaign?->template?->name ?? '-' }}
                    @if ($campaign?->template?->category)
                      <span class="mt-0.5 block text-xs text-text-muted">{{ $campaign->template->category }}</span>
                    @endif
                  </td>
                  <td class="fd-table-cell p-2">{{ number_format($row->charge_count) }}</td>
                  <td class="fd-table-cell p-2 font-semibold text-red-600">-₹ {{ number_format((float) $row->total_amount, 2) }}</td>
                  <td class="fd-table-cell p-2">{{ $row->last_charged_at?->format('d M Y h:i A') ?? '-' }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-6 text-center text-sm text-text-muted">No campaign wallet charges found yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if ($summaries->hasPages())
          <x-ui.table-pagination :paginator="$summaries" />
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
