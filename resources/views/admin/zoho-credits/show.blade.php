<x-admin.layout title="Credit request - Admin" active="admin.zoho-credits.index">
  <div class="p-4">
    <a href="{{ route('admin.zoho-credits.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Credit requests</a>
    <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</h1>
  </div>

  <section class="mx-4 mb-4 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
      @foreach ([
        'Customer' => $row->tenant_id,
        'Source' => $row->source,
        'Amount' => $row->currency.' '.$row->amount,
        'Status' => $row->status,
        'External id' => $row->external_id,
        'Invoice number' => $row->invoice_number,
        'Wallet credited' => optional($row->wallet_credited_at)->toDayDateTimeString(),
        'Received' => optional($row->created_at)->toDayDateTimeString(),
      ] as $label => $value)
        <div>
          <dt class="text-xs uppercase tracking-wide text-text-subtle">{{ $label }}</dt>
          <dd class="mt-1 font-semibold">{{ $value ?: '—' }}</dd>
        </div>
      @endforeach
    </dl>

    @if ($row->last_error)
      <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $row->last_error }}</div>
    @endif
  </section>

  <section class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    <h2 class="text-lg font-bold">Metadata</h2>
    @if (empty($row->metadata))
      <p class="mt-3 text-sm text-text-subtle">No metadata stored.</p>
    @else
      <pre class="mt-3 overflow-x-auto rounded-lg bg-surface p-3 text-xs">{{ json_encode($row->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif
  </section>
</x-admin.layout>
