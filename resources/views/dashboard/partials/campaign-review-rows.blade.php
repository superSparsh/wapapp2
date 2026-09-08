@php
  $campaignRecipients = $campaignRecipients ?? collect();
  $selectedCampaign = $selectedCampaign ?? null;
@endphp

@forelse ($campaignRecipients as $index => $recipient)
  @php
    $statusVariant = match ($recipient->status?->value) {
      'delivered' => 'done',
      'failed' => 'rejected',
      'read' => 'approved',
      'response' => 'active',
      'sent' => 'sent',
      'unsubscribed' => 'inactive',
      default => 'pending',
    };
  @endphp
  <tr class="border-t border-divider bg-elevated">
    <td class="fd-table-cell p-2 pl-4">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
    <td class="fd-table-name p-2">{{ $recipient->contact?->name ?: '—' }}</td>
    <td class="fd-table-cell p-2 text-xs">{{ $recipient->contact_phone ?: ($recipient->contact?->phone ?? '—') }}</td>
    <td class="fd-table-cell p-2 text-xs">{{ $selectedCampaign?->name ?? '—' }}</td>
    <td class="fd-table-cell p-2 text-xs">
      {{ optional($recipient->sent_at ?? $recipient->delivered_at ?? $recipient->created_at)->format('d M Y h:i A') ?? '—' }}
    </td>
    <td class="p-2 text-center">
      <x-ui.status-chip :label="$recipient->status?->label() ?? 'Pending'" :variant="$statusVariant" />
    </td>
  </tr>
@empty
  <tr class="border-t border-divider bg-elevated">
    <td colspan="6" class="fd-table-cell p-4 text-sm text-text-muted">
      @if ($selectedCampaign)
        No recipient logs for this campaign yet.
      @else
        No campaigns migrated yet.
      @endif
    </td>
  </tr>
@endforelse
