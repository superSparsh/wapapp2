<x-admin.layout title="Purge review - Admin" active="admin.data-purge.index">
  <div class="p-4">
    <a href="{{ route('admin.data-purge.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Candidates</a>
    <h1 class="mt-1 text-2xl font-bold">{{ $tenant->company_name ?: $tenant->name }}</h1>
  </div>

  <section class="mx-4 mb-8 max-w-2xl rounded-[20px] border border-border bg-elevated p-5">
    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
      <div><dt class="text-xs uppercase text-text-subtle">Status</dt><dd class="font-semibold">{{ $tenant->status?->value }}</dd></div>
      <div><dt class="text-xs uppercase text-text-subtle">Valid until</dt><dd class="font-semibold">{{ $valid_until ?: '—' }}</dd></div>
      <div><dt class="text-xs uppercase text-text-subtle">Purge requested</dt><dd class="font-semibold">{{ $purge_requested_at ?: '—' }}</dd></div>
      <div><dt class="text-xs uppercase text-text-subtle">Plan</dt><dd class="font-semibold">{{ $tenant->plan?->name ?: '—' }}</dd></div>
    </dl>

    <p class="mt-4 text-sm text-text-subtle">
      Marking for purge suspends the account and records an admin request. Hard tenant DB deletion stays a separate ops step.
    </p>

    <div class="mt-5 flex flex-wrap gap-2">
      @if ($purge_requested_at)
        <form method="POST" action="{{ route('admin.data-purge.unmark', $tenant) }}">@csrf
          <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold">Clear purge mark</button>
        </form>
      @else
        <form method="POST" action="{{ route('admin.data-purge.mark', $tenant) }}" data-confirm="Suspend and mark this customer for purge?" data-confirm-title="Confirm purge mark" data-confirm-variant="danger">
          @csrf
          <button class="rounded-lg bg-red-500 px-3 py-2 text-xs font-semibold text-white">Mark for purge</button>
        </form>
      @endif
      <a href="{{ route('admin.customers.show', $tenant) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold">Open customer</a>
    </div>
  </section>
</x-admin.layout>
