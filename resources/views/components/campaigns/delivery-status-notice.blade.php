@props([
    'campaign',
    'metrics' => null,
])

@php
  $notice = app(\App\Domains\Campaigns\Support\CampaignDeliveryStatusNotice::class);
  $show = $metrics !== null && $notice->shouldShow($campaign, $metrics);
@endphp

@if ($show)
  <div
    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-relaxed text-text-body"
    role="status"
    aria-live="polite"
  >
    <p class="font-semibold text-text-primary">Delivery statuses are being updated</p>
    <p class="mt-1 text-text-muted">{{ \App\Domains\Campaigns\Support\CampaignDeliveryStatusNotice::MESSAGE }}</p>
  </div>
@endif
