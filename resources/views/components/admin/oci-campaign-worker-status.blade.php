@props([
    'ociWorker' => [],
])

@php
  $enabled = (bool) ($ociWorker['enabled'] ?? false);
  $ocid = $ociWorker['ocid'] ?? null;
  $active = is_string($ocid) && $ocid !== '';
  $last = $ociWorker['last_session'] ?? null;
  $campaignIds = $ociWorker['active_campaign_ids'] ?? [];
@endphp

<div class="rounded-[20px] border border-border bg-elevated p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="text-lg font-bold text-text-primary">OCI campaign worker</h2>
      <p class="mt-1 text-sm text-text-subtle">Shared ephemeral container — live status and last session duration (admin only).</p>
    </div>
    <span @class([
      'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
      'bg-green-50 text-green-700 ring-green-200' => $active,
      'bg-surface text-text-subtle ring-border' => ! $active,
    ])>{{ $active ? 'Container active' : 'No container' }}</span>
  </div>

  <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
    <div>
      <dt class="text-xs uppercase tracking-wide text-text-subtle">Feature</dt>
      <dd class="mt-1 font-semibold text-text-primary">{{ $enabled ? 'Enabled' : 'Disabled' }}</dd>
    </div>
    <div>
      <dt class="text-xs uppercase tracking-wide text-text-subtle">Active duration</dt>
      <dd class="mt-1 font-semibold tabular-nums text-text-primary">{{ $active ? ($ociWorker['active_for_humans'] ?? '—') : '—' }}</dd>
    </div>
    <div>
      <dt class="text-xs uppercase tracking-wide text-text-subtle">Started at</dt>
      <dd class="mt-1 font-semibold text-text-primary">
        @if (! empty($ociWorker['started_at']))
          {{ \Illuminate\Support\Carbon::parse($ociWorker['started_at'])->timezone(config('app.timezone'))->format('d M Y h:i A') }}
        @else
          —
        @endif
      </dd>
    </div>
    <div>
      <dt class="text-xs uppercase tracking-wide text-text-subtle">Active campaigns</dt>
      <dd class="mt-1 font-semibold tabular-nums text-text-primary">{{ count($campaignIds) }}</dd>
    </div>
  </dl>

  @if ($active)
    <p class="mt-3 break-all font-mono text-[11px] text-text-subtle">OCID: {{ $ocid }}</p>
    @if (($ociWorker['max_recipients'] ?? 0) > 0)
      <p class="mt-1 text-xs text-text-subtle">Peak recipients demand: {{ number_format((int) $ociWorker['max_recipients']) }}</p>
    @endif
  @endif

  <div class="mt-4 rounded-xl border border-border bg-surface/60 p-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle">Last container session</p>
    @if (is_array($last) && ! empty($last['active_for_humans']))
      <p class="mt-2 text-sm font-semibold text-text-primary">
        Ran for <span class="tabular-nums">{{ $last['active_for_humans'] }}</span>
      </p>
      <p class="mt-1 text-xs text-text-subtle">
        {{ ! empty($last['started_at']) ? \Illuminate\Support\Carbon::parse($last['started_at'])->timezone(config('app.timezone'))->format('d M Y h:i A') : '—' }}
        →
        {{ ! empty($last['ended_at']) ? \Illuminate\Support\Carbon::parse($last['ended_at'])->timezone(config('app.timezone'))->format('d M Y h:i A') : '—' }}
      </p>
    @else
      <p class="mt-2 text-sm text-text-subtle">No closed session recorded yet.</p>
    @endif
  </div>
</div>
