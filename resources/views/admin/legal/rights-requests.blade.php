<x-admin.layout title="Rights requests - Admin" active="admin.rights-requests.index">
  @php
    $statusStyles = [
      'open' => 'bg-amber-50 text-amber-800 ring-amber-200',
      'in_progress' => 'bg-blue-50 text-blue-800 ring-blue-200',
      'resolved' => 'bg-green-50 text-green-700 ring-green-200',
      'rejected' => 'bg-red-50 text-red-700 ring-red-200',
      'closed' => 'bg-surface text-text-muted ring-border',
    ];
  @endphp

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-text-primary">Rights / grievance requests</h1>
        <p class="mt-1 max-w-3xl text-sm text-text-subtle">
          Data Principal requests against {{ config('legal.product_name') }}. SLA {{ $slaDays }} days.
          Contact mailbox: <a href="mailto:{{ $grievanceEmail }}" class="underline">{{ $grievanceEmail }}</a>.
        </p>
      </div>
      <a href="{{ route('admin.dpdp-readiness') }}" class="text-sm font-semibold text-primary-2 underline">DPDP readiness</a>
    </div>

    @if (session('status'))
      <div class="rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <section class="overflow-hidden rounded-[20px] border border-border bg-elevated">
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-border bg-surface/50 text-xs uppercase tracking-wide text-text-muted">
            <tr>
              <th class="px-4 py-3">Requester</th>
              <th class="px-4 py-3">Type</th>
              <th class="px-4 py-3">Due</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Update</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            @forelse ($requests as $item)
              <tr>
                <td class="px-4 py-3 align-top">
                  <p class="font-semibold text-text-primary">{{ $item->requester_name }}</p>
                  <p class="text-xs text-text-muted">{{ $item->requester_email }}</p>
                  <p class="mt-2 max-w-md text-text-body">{{ $item->description }}</p>
                  @if ($item->nominee_name)
                    <p class="mt-1 text-xs text-text-muted">Nominee: {{ $item->nominee_name }} ({{ $item->nominee_email }})</p>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">{{ $item->request_type->label() }}</td>
                <td class="px-4 py-3 align-top">
                  {{ $item->due_at?->format('d M Y') ?? '—' }}
                  @if ($item->due_at && $item->due_at->isPast() && ! $item->status->isTerminal())
                    <span class="mt-1 block text-xs font-semibold text-red-600">Overdue</span>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">
                  <span @class([
                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                    $statusStyles[$item->status->value] ?? $statusStyles['closed'],
                  ])>{{ $item->status->label() }}</span>
                </td>
                <td class="px-4 py-3 align-top">
                  <form method="POST" action="{{ route('admin.rights-requests.update', $item) }}" class="flex min-w-[220px] flex-col gap-2">
                    @csrf
                    @method('PUT')
                    <select name="status" class="rounded-lg border border-border px-2 py-1.5 text-xs">
                      @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($item->status === $status)>{{ $status->label() }}</option>
                      @endforeach
                    </select>
                    <textarea name="admin_note" rows="2" class="rounded-lg border border-border px-2 py-1.5 text-xs" placeholder="Internal note">{{ $item->admin_note }}</textarea>
                    <button type="submit" class="rounded-lg bg-primary-2 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-4 py-8 text-center text-text-muted">No rights requests yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($requests->hasPages())
        <div class="border-t border-border px-4 py-3">{{ $requests->links() }}</div>
      @endif
    </section>
  </div>
</x-admin.layout>
