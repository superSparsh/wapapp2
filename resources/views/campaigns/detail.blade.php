<x-layouts.app title="{{ $campaign->name }} Recipients - WapApp" active="campaigns.index">
  <x-campaigns.campaign-header :campaign="$campaign" activeTab="recipients">
    <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
      <div class="flex w-full flex-col gap-2">
        <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">
          {{ $campaign->name }}
        </h2>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Detailed recipient log for this campaign.
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <h3 class="text-xl font-bold leading-[1.5] text-text-primary">Recipients</h3>
          <span class="rounded bg-elevated px-2 py-1 text-xs font-medium text-text-muted">
            {{ number_format($recipients->total()) }} total
          </span>
        </div>
        <div class="flex items-center gap-3">
          <form method="GET" action="{{ route('campaigns.statistics.detail', $campaign) }}" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="flex min-w-[160px] items-center gap-2.5 rounded-lg bg-elevated p-3 text-sm">
              <option value="">All Statuses</option>
              @foreach (\App\Enums\CampaignRecipientStatus::cases() as $statusEnum)
                <option value="{{ $statusEnum->value }}" @selected($currentStatus === $statusEnum->value)>
                  {{ $statusEnum->label() }}
                </option>
              @endforeach
            </select>
          </form>
          <a
            href="{{ route('campaigns.statistics.export', $campaign) }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-100"
          >
            <img src="{{ asset('images/automation/export-csv.svg') }}" alt="" class="size-4" width="16" height="16">
            Export CSV
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
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Sent At</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Delivered At</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Failed At</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($recipients as $index => $recipient)
                @php $serial = ($recipients->currentPage() - 1) * $recipients->perPage() + $index + 1; @endphp
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $serial }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $recipient->contact_phone ?? 'N/A' }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $recipient->contact?->name ?? 'N/A' }}</td>
                  <td class="p-2 text-center">
                    @php
                      $statusLabel = $recipient->status?->label() ?? 'Unknown';
                      $statusColor = match ($recipient->status) {
                          \App\Enums\CampaignRecipientStatus::Delivered => 'text-blue-600 bg-[rgba(59,130,246,0.1)]',
                          \App\Enums\CampaignRecipientStatus::Read => 'text-green-600 bg-[rgba(16,185,129,0.1)]',
                          \App\Enums\CampaignRecipientStatus::Response => 'text-purple-600 bg-[rgba(142,68,173,0.1)]',
                          \App\Enums\CampaignRecipientStatus::Failed => 'text-red-600 bg-[rgba(239,68,68,0.1)]',
                          \App\Enums\CampaignRecipientStatus::Sent => 'text-gray-600 bg-[rgba(107,114,128,0.1)]',
                          \App\Enums\CampaignRecipientStatus::Unsubscribed => 'text-orange-600 bg-[rgba(245,158,11,0.1)]',
                          default => 'text-text-muted bg-[rgba(0,0,0,0.05)]',
                      };
                    @endphp
                    <span class="inline-flex items-center justify-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap {{ $statusColor }}">
                      {{ $statusLabel }}
                    </span>
                  </td>
                  <td class="min-w-[220px] max-w-[360px] p-2 text-[13px] font-normal leading-[1.5] break-words text-text-body">
                    {{ $recipient->status === \App\Enums\CampaignRecipientStatus::Failed
                        ? ($recipient->failure_reason ?: 'N/A')
                        : '—' }}
                  </td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $recipient->sent_at?->format('d M Y h:i A') ?? '—' }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $recipient->delivered_at?->format('d M Y h:i A') ?? '—' }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $recipient->failed_at?->format('d M Y h:i A') ?? '—' }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="8" class="p-8 text-center text-sm text-text-body">No recipients recorded yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($recipients->hasPages())
          <x-ui.table-pagination :paginator="$recipients" />
        @endif
      </div>
    </section>
  </x-campaigns.campaign-header>
  @push('scripts')
  <script src="{{ asset('js/campaigns/campaigns.js') }}" defer></script>
  @endpush
</x-layouts.app>
