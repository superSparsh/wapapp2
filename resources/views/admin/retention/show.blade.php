<x-admin.layout title="Retention — {{ $tenant->company_name ?: $tenant->name }}" active="admin.retention.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.retention.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Retention</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $tenant->company_name ?: $tenant->name }}</h1>
      <p class="text-sm text-text-subtle">{{ $tenant->id }}</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.customers.show', $tenant) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Customer profile</a>
      <form method="POST" action="{{ route('admin.customers.login-as', $tenant) }}">
        @csrf
        <button class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Login as</button>
      </form>
    </div>
  </div>

  <section class="grid gap-4 p-4 pt-0 xl:grid-cols-3">
    <div class="rounded-[20px] border border-border bg-elevated p-5 xl:col-span-2">
      <h2 class="text-lg font-bold">Account snapshot</h2>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
        <div><dt class="text-xs uppercase text-text-subtle">Status</dt><dd class="font-semibold">{{ $tenant->status?->value }}</dd></div>
        <div><dt class="text-xs uppercase text-text-subtle">Plan</dt><dd class="font-semibold">{{ $tenant->plan?->name ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-text-subtle">Valid until</dt><dd class="font-semibold">{{ $valid_until?->toDateString() ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-text-subtle">Days left</dt><dd class="font-semibold">{{ $days_left === null ? '—' : $days_left }}</dd></div>
        <div><dt class="text-xs uppercase text-text-subtle">Email</dt><dd class="font-semibold">{{ $tenant->email ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-text-subtle">Phone</dt><dd class="font-semibold">{{ $tenant->phone ?: '—' }}</dd></div>
      </dl>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Add follow-up note</h2>
      <form method="POST" action="{{ route('admin.retention.notes.store', $tenant) }}" class="mt-4 flex flex-col gap-3">
        @csrf
        <select name="action_type" class="rounded-lg border border-border px-3 py-2 text-sm">
          <option value="retention_note">Note</option>
          <option value="called">Called</option>
          <option value="emailed">Emailed</option>
          <option value="renewed">Marked renewed</option>
        </select>
        <textarea name="note" rows="4" required maxlength="2000" class="rounded-lg border border-border px-3 py-2 text-sm" placeholder="Outreach notes…"></textarea>
        <button class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Save note</button>
      </form>
    </div>
  </section>

  <section class="p-4 pt-0">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold">Retention notes</h2>
      <div class="mt-4 divide-y divide-border">
        @forelse ($notes as $note)
          <div class="py-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-text-subtle">
              <span>{{ $note['admin_name'] ?? 'Admin' }} · {{ $note['action_type'] ?? 'note' }}</span>
              <span>{{ isset($note['created_at']) ? \Illuminate\Support\Carbon::parse($note['created_at'])->diffForHumans() : '' }}</span>
            </div>
            <p class="mt-1 text-sm text-text-primary">{{ $note['note'] ?? '' }}</p>
          </div>
        @empty
          <p class="py-4 text-sm text-text-subtle">No notes yet.</p>
        @endforelse
      </div>
    </div>
  </section>
</x-admin.layout>
