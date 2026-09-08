<x-layouts.app title="{{ $campaign->name }} Statistics - WapApp" active="campaigns.index">
  <x-campaigns.campaign-header :campaign="$campaign" activeTab="statistics">
    <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
      <div class="flex flex-col gap-1">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $campaign->name }}</h2>
        <p class="text-sm font-normal leading-[1.4] text-text-subtle/50">
          Total {{ number_format($metrics['total']) }} Messages
          @if ($campaign->whatsappLine)
            From {{ $campaign->whatsappLine->displayPhone() }}
          @endif
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-xl font-bold leading-[1.5] text-text-primary">Statistics</h3>
        <div class="flex flex-wrap items-center gap-2">
          <a
            href="{{ route('campaigns.statistics', $campaign) }}"
            class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-surface"
          >
            <img src="{{ asset('images/campaigns/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </a>
          @if ($campaign->isSending() || $campaign->isPaused())
            <a
              href="{{ request()->fullUrlWithQuery(['modal' => 'resend-failed']) }}"
              class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90"
            >
              <img src="{{ asset('images/campaigns/stats/resend.svg') }}" alt="" class="size-5" width="20" height="20">
              Resend
            </a>
          @endif
        </div>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="rounded-lg bg-elevated p-3">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          @php
            $detailRoute = fn (?string $status = null) => route('campaigns.statistics.detail', array_filter([
                'bulkCampaign' => $campaign,
                'status' => $status,
            ]));

            $stats = [
                [
                    'title' => 'Total Recipients',
                    'count' => number_format($metrics['total']),
                    'total' => number_format($metrics['total']),
                    'percent' => '100',
                    'color' => 'blue',
                    'icon' => 'task.svg',
                    'status' => null,
                ],
                [
                    'title' => 'Delivered',
                    'count' => number_format($metrics['delivered']),
                    'total' => number_format($metrics['total']),
                    'percent' => str_replace('%', '', $metrics['delivered_pct']),
                    'color' => 'green',
                    'icon' => 'send.svg',
                    'status' => 'delivered',
                ],
                [
                    'title' => 'Failed',
                    'count' => number_format($metrics['failed']),
                    'total' => number_format($metrics['total']),
                    'percent' => str_replace('%', '', $metrics['failed_pct']),
                    'color' => 'red',
                    'icon' => 'warning.svg',
                    'status' => 'failed',
                ],
                [
                    'title' => 'Read',
                    'count' => number_format($metrics['read']),
                    'total' => number_format($metrics['total']),
                    'percent' => str_replace('%', '', $metrics['read_pct']),
                    'color' => 'emerald',
                    'icon' => 'tick-circle.svg',
                    'status' => 'read',
                ],
                [
                    'title' => 'Response',
                    'count' => number_format($metrics['response']),
                    'total' => number_format($metrics['total']),
                    'percent' => str_replace('%', '', $metrics['response_pct']),
                    'color' => 'purple',
                    'icon' => 'task-square.svg',
                    'status' => 'response',
                ],
                [
                    'title' => 'Unsubscribed',
                    'count' => number_format($metrics['unsubscribed']),
                    'total' => number_format($metrics['total']),
                    'percent' => str_replace('%', '', $metrics['unsubscribed_pct']),
                    'color' => 'orange',
                    'icon' => 'group.svg',
                    'status' => 'unsubscribed',
                ],
            ];
          @endphp

          @foreach ($stats as $stat)
            <x-ui.campaign-metric-card
              :title="$stat['title']"
              :count="$stat['count']"
              :total="$stat['total']"
              :percent="$stat['percent']"
              :color="$stat['color']"
              :icon-src="asset('images/campaigns/stats/' . $stat['icon'])"
              :details-href="$detailRoute($stat['status'])"
              :details-link-color="$stat['color'] === 'red' || $stat['color'] === 'orange' ? 'text-[#71b56d]' : 'text-green-500'"
            />
          @endforeach
        </div>
      </div>
    </section>
  </x-campaigns.campaign-header>

  @if (request('modal') === 'resend-failed')
    <x-dashboard.resend-failed-modal :show="true" :close-href="request()->url()" />
  @endif

  @push('scripts')
  <script src="{{ asset('js/campaigns/campaigns.js') }}" defer></script>
  @endpush
</x-layouts.app>
