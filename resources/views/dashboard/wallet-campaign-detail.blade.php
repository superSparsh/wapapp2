@php
  $lineLabel = $campaign->whatsappLine?->displayLabel();
@endphp

<x-layouts.app title="{{ $campaign->name }} Wallet - WapApp" active="dashboard">
  <div class="flex flex-col">
    <div class="flex flex-col gap-4 p-4">
      <x-dashboard.page-header
        title="{{ $campaign->name }}"
        subtitle="Recipient-level wallet charges for this campaign."
      />

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-sm text-text-muted">
          <a href="{{ route('dashboard.wallet.campaigns') }}" class="font-semibold text-green-500 hover:underline">By campaign</a>
          <span>/</span>
          <span class="text-text-primary">{{ $campaign->name }}</span>
          @if ($campaign->audience?->name)
            <span>· {{ $campaign->audience->name }}</span>
          @endif
          @if ($campaign->template?->name)
            <span>· {{ $campaign->template->name }}</span>
          @endif
          @if ($lineLabel)
            <span>· {{ $lineLabel }}</span>
          @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="rounded-lg border border-border bg-elevated px-4 py-2.5 text-sm">
            <span class="text-text-muted">Total debit</span>
            <span class="ml-2 font-semibold text-red-600">-₹ {{ number_format((float) $totalAmount, 2) }}</span>
          </div>
          <a
            href="{{ route('dashboard.wallet.campaign.export', $campaign) }}"
            class="fd-btn inline-flex items-center justify-center gap-2 rounded border border-green-500 bg-elevated px-4 py-3 text-sm font-semibold text-green-500 hover:bg-surface"
          >
            <img src="{{ asset('images/automation/export-csv.svg') }}" alt="" class="size-4" width="16" height="16">
            Export CSV
          </a>
        </div>
      </div>
    </div>

    <section class="bg-surface px-4 pb-4">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head min-w-[140px] p-2">Phone</th>
                <th class="fd-table-head min-w-[140px] p-2">Name</th>
                <th class="fd-table-head w-[120px] p-2">Recipient</th>
                <th class="fd-table-head w-[120px] p-2">Amount</th>
                <th class="fd-table-head w-[120px] p-2">Category</th>
                <th class="fd-table-head min-w-[220px] p-2">Description</th>
                <th class="fd-table-head min-w-[160px] p-2">Msg ID</th>
                <th class="fd-table-head w-[180px] p-2">Date</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($charges as $index => $transaction)
                @php
                  $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
                  $recipient = $transaction->campaign_recipient ?? null;
                  $phone = (string) (
                    $meta['contact_phone']
                    ?? $recipient?->contact_phone
                    ?? $recipient?->contact?->phone
                    ?? ''
                  );
                  $name = (string) ($recipient?->contact?->name ?? '');
                  $category = (string) ($meta['pricing_category'] ?? $meta['template_category'] ?? $meta['legacy_category'] ?? '-');
                  $msgId = (string) ($meta['external_message_id'] ?? $meta['legacy_msg_id'] ?? '-');
                @endphp
                <tr class="border-t border-divider bg-elevated">
                  <td class="fd-table-cell p-2 pl-4">{{ str_pad((string) ($charges->firstItem() + $index), 2, '0', STR_PAD_LEFT) }}</td>
                  <td class="fd-table-cell p-2">{{ $phone !== '' ? $phone : '-' }}</td>
                  <td class="fd-table-cell p-2">{{ $name !== '' ? $name : '-' }}</td>
                  <td class="fd-table-cell p-2">{{ $recipient?->status?->label() ?? '-' }}</td>
                  <td class="fd-table-cell p-2 font-semibold text-red-600">-₹ {{ number_format((float) $transaction->amount, 2) }}</td>
                  <td class="fd-table-cell p-2">{{ $category }}</td>
                  <td class="p-2">
                    <p class="fd-table-name text-text-subtle">{{ $transaction->description ?: '-' }}</p>
                  </td>
                  <td class="fd-table-cell p-2 break-all">{{ $msgId !== '' ? $msgId : '-' }}</td>
                  <td class="fd-table-cell p-2">{{ $transaction->created_at?->format('d M Y h:i A') ?? '-' }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="9" class="p-6 text-center text-sm text-text-muted">No wallet charges found for this campaign.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if ($charges->hasPages())
          <x-ui.table-pagination :paginator="$charges" />
        @endif
      </div>
    </section>
  </div>
</x-layouts.app>
