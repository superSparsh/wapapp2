@php
    $statusVariant = fn (string $status) => match ($status) {
        'Draft' => 'new',
        'Scheduled' => 'fd-draft',
        'Sending' => 'sending',
        'Completed' => 'fd-approved',
        'Paused' => 'paused',
        'Cancelled' => 'cancelled',
        default => 'default',
    };
@endphp

<x-layouts.app title="Active Campaigns - WapApp" active="campaigns.index">
  <div class="flex flex-col bg-surface">
    <x-ui.sub-nav :items="[
      ['route' => 'campaigns.index', 'label' => 'All'],
      ['route' => 'campaigns.active', 'label' => 'Active'],
      ['route' => 'campaigns.scheduled', 'label' => 'Scheduled'],
    ]" />

    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">Active Campaigns</h1>
        <p class="fd-page-note">Campaigns currently sending.</p>
      </div>

      <x-ui.listing-toolbar
          :action="route('campaigns.active')"
          search-name="search"
          :search-value="$search ?? ''"
          search-placeholder="Search campaigns..."
          :current-sort="$currentSort ?? 'created_at'"
          :current-direction="$currentDirection ?? 'desc'"
          :sort-options="[
            ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
            ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
            ['value' => 'total_recipients', 'label' => 'Most recipients', 'direction' => 'desc'],
          ]"
        >
          <x-slot:actions>
            <a href="{{ route('campaigns.create.start') }}" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
              <img src="{{ asset('images/campaigns/add.svg') }}" alt="" class="size-5" width="20" height="20">
              Create New Campaign
            </a>
          </x-slot:actions>
        </x-ui.listing-toolbar>
    </div>

    <section class="p-4 pt-0">
      @if ($paginator->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-elevated p-12">
          <p class="text-sm font-medium text-text-subtle">No active campaigns.</p>
        </div>
      @else
        <x-ui.data-table :headers="['SI. No', 'Campaign Name', 'Audience Name', 'Communication Status', 'Status', 'Actions']" :paginator="$paginator" :column-widths="['w-[54px]', 'w-[320px]', 'w-[220px]', 'w-[220px]', '', 'w-[160px]']">
          @foreach ($campaigns as $index => $campaign)
            @php $card = app(\App\Domains\Campaigns\Services\CampaignPresenter::class)->indexCard($campaign); @endphp
            <tr class="bg-elevated">
              <td class="fd-table-cell w-[54px] p-2 align-middle">{{ str_pad((string) ($paginator->firstItem() + $index), 2, '0', STR_PAD_LEFT) }}</td>
              <td class="w-[320px] p-2 align-middle">
                <a href="{{ route('campaigns.show', $campaign) }}" class="fd-table-name hover:text-green-500">{{ $card['name'] }}</a>
                <p class="fd-table-cell text-[13px]">Created at: {{ $card['created_at'] }}</p>
              </td>
              <td class="fd-table-cell w-[220px] p-2 align-middle">{{ $card['audience'] }}</td>
              <td class="w-[220px] p-2 align-middle">
                <x-ui.comm-status
                  :delivered="$card['delivered']"
                  :read="$card['read']"
                  :response="$card['response']"
                  :failed="$card['failed']"
                />
              </td>
              <td class="p-2 align-middle">
                <div class="flex flex-col items-center justify-center gap-2.5">
                  <x-ui.status-chip :label="$card['status_label']" :variant="$card['status_variant']" />
                </div>
              </td>
              <td class="w-[160px] p-2 align-middle">
                <div class="flex items-center justify-center gap-6">
                  <a href="{{ route('campaigns.statistics', $campaign) }}" class="flex size-5 items-center justify-center" aria-label="Statistics">
                    <img src="{{ asset('images/campaigns/chart.svg') }}" alt="" class="size-5" width="20" height="20">
                  </a>
                  <form method="POST" action="{{ route('campaigns.duplicate', $campaign) }}" class="inline">
                    @csrf
                    <button type="submit" class="flex size-5 items-center justify-center" aria-label="Duplicate">
                      <img src="{{ asset('images/campaigns/copy-figma.svg') }}" alt="" class="size-5" width="20" height="20">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </x-ui.data-table>
      @endif
    </section>
  </div>
</x-layouts.app>
