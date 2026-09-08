@props([
    'delivered' => '0/0',
    'read' => '0/0',
    'response' => '0/0',
    'failed' => null,
])

<div class="flex flex-wrap items-center justify-center gap-2.5">
  <span class="rounded bg-[rgba(59,130,246,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-stat-blue">{{ $delivered }} Delivered</span>
  <span class="rounded bg-[rgba(16,185,129,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-stat-emerald">{{ $read }} Read</span>
  <span class="rounded bg-[rgba(156,163,175,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-text-muted">{{ $response }} Response</span>
  @if ($failed !== null)
    <span class="rounded bg-[rgba(239,68,68,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] text-danger">{{ $failed }} Failed</span>
  @endif
</div>
