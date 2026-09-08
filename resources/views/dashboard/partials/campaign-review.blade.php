@php
  /** @var \Illuminate\Support\Collection<int, \App\Models\Campaign> $recentCampaigns */
  $recentCampaigns = $recentCampaigns ?? collect();
  /** @var \App\Models\Campaign|null $selectedCampaign */
  $selectedCampaign = $selectedCampaign ?? null;
  /** @var \Illuminate\Support\Collection<int, \App\Models\CampaignRecipient> $campaignRecipients */
  $campaignRecipients = $campaignRecipients ?? collect();
  $total = (int) ($selectedCampaign?->total_recipients ?? 0);
  $delivered = (int) ($selectedCampaign?->total_delivered ?? 0);
  $failed = (int) ($selectedCampaign?->total_failed ?? 0);
  $read = (int) ($selectedCampaign?->total_read ?? 0);
  $response = (int) ($selectedCampaign?->total_response ?? 0);
  $unsubscribed = (int) ($selectedCampaign?->total_unsubscribed ?? 0);
  $pct = fn (int $val): string => $total > 0 ? (string) number_format(($val / $total) * 100) : '0';
  $detailsBase = $selectedCampaign
    ? route('campaigns.statistics.detail', $selectedCampaign)
    : route('campaigns.index');
  $statsUrl = $selectedCampaign
    ? route('campaigns.statistics', $selectedCampaign)
    : route('campaigns.index');
@endphp

<section
  class="px-4 pb-4"
  data-dashboard-campaign-review
  data-campaign-review-url="{{ route('dashboard.campaign-review') }}"
>
  <div class="flex flex-col gap-4 rounded-lg bg-elevated p-4">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <h2 class="fd-section-title mr-auto">Review Your Recently Sent Campaigns</h2>
      <button type="button" data-campaign-review-refresh class="fd-btn inline-flex items-center gap-2 rounded-lg border border-border bg-elevated px-3 py-2 text-sm font-medium">
        <x-icons.nav-icon name="refresh-2" class="size-4" />
        Refresh
      </button>
    </div>

    <div class="flex flex-col gap-3">
      <label class="fd-label" for="campaign_id">Select a campaign from below list</label>
      <select
        id="campaign_id"
        name="campaign_id"
        data-campaign-review-select
        class="w-full rounded-xl border border-border bg-elevated p-3.5 text-sm font-medium leading-[1.4] text-text-primary outline-none focus:border-green-500"
      >
        @forelse ($recentCampaigns as $campaign)
          <option value="{{ $campaign->uuid }}" @selected($selectedCampaign?->uuid === $campaign->uuid)>
            {{ $campaign->name }}
          </option>
        @empty
          <option value="">No campaigns yet</option>
        @endforelse
      </select>
    </div>

    <div class="overflow-hidden rounded-xl border border-divider shadow-[0px_4px_12px_0px_rgba(0,0,0,0.04)]">
      <div class="max-h-[260px] overflow-auto">
        <table class="w-full min-w-[900px] text-left">
          <thead class="sticky top-0 z-10">
            <tr class="bg-elevated">
              <th class="fd-table-head w-[54px] bg-elevated p-2">SI. No</th>
              <th class="fd-table-head w-[160px] bg-elevated p-2">Name</th>
              <th class="fd-table-head w-[160px] bg-elevated p-2">WhatsApp Number</th>
              <th class="fd-table-head bg-elevated p-2">Campaign</th>
              <th class="fd-table-head w-[180px] bg-elevated p-2">Sent At</th>
              <th class="fd-table-head w-[120px] bg-elevated p-2 text-center">Status</th>
            </tr>
          </thead>
          <tbody data-campaign-review-rows>
            @include('dashboard.partials.campaign-review-rows', [
              'campaignRecipients' => $campaignRecipients,
              'selectedCampaign' => $selectedCampaign,
            ])
          </tbody>
        </table>
      </div>
      <div class="flex items-center justify-end border-t border-divider bg-elevated px-3 py-2">
        <a
          href="{{ $statsUrl }}"
          data-campaign-review-more
          @class([
            'fd-btn inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-green-600 hover:bg-green-50',
            'pointer-events-none opacity-50' => ! $selectedCampaign,
          ])
        >
          More
          <x-icons.nav-icon name="arrow-right" class="size-4" />
        </a>
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
      <h3 class="fd-section-title mr-auto" data-campaign-review-heading>Send to {{ number_format($total) }} recipients</h3>
      <button type="button" data-campaign-review-refresh class="fd-btn inline-flex items-center gap-2 rounded-lg border border-border bg-elevated px-3 py-2 text-sm font-medium">
        <x-icons.nav-icon name="refresh-2" class="size-4" />
        Refresh
      </button>
    </div>

    <div class="grid gap-3 xl:grid-cols-3" data-campaign-review-metrics>
      <x-ui.campaign-metric-card data-metric="total" title="Total Recipients" :count="(string) $total" :total="(string) $total" percent="100" color="blue" :details-href="$detailsBase" />
      <x-ui.campaign-metric-card data-metric="delivered" title="Delivered" :count="(string) $delivered" :total="(string) $total" :percent="$pct($delivered)" color="green" icon="send" details-href="{{ $detailsBase }}?status=delivered" />
      <x-ui.campaign-metric-card data-metric="failed" title="Failed" :count="(string) $failed" :total="(string) $total" :percent="$pct($failed)" color="red" icon="warning" details-href="{{ $detailsBase }}?status=failed" />
      <x-ui.campaign-metric-card data-metric="read" title="Read" :count="(string) $read" :total="(string) $total" :percent="$pct($read)" color="emerald" icon="tick-circle" details-href="{{ $detailsBase }}?status=read" />
      <x-ui.campaign-metric-card data-metric="response" title="Response" :count="(string) $response" :total="(string) $total" :percent="$pct($response)" color="purple" icon="task-square" details-href="{{ $detailsBase }}?status=response" />
      <x-ui.campaign-metric-card data-metric="unsubscribed" title="Unsubscribed" :count="(string) $unsubscribed" :total="(string) $total" :percent="$pct($unsubscribed)" color="orange" icon="group" details-href="{{ $detailsBase }}?status=unsubscribed" />
    </div>
  </div>
</section>
