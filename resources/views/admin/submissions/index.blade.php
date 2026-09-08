<x-admin.layout title="Customer Submissions - Admin" active="admin.submissions.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Customer submissions</h1>
    <p class="text-sm text-text-subtle opacity-70">Readiness and onboarding forms received from prospects.</p>
  </div>

  <div class="mx-4 mb-4 flex flex-wrap gap-2">
    @foreach (['readiness' => 'Readiness', 'onboarding' => 'Onboarding'] as $key => $label)
      <a
        href="{{ route('admin.submissions.index', ['tab' => $key]) }}"
        @class([
          'rounded-lg px-3 py-2 text-xs font-semibold',
          'bg-green-500 text-white' => $tab === $key,
          'border border-border hover:bg-surface' => $tab !== $key,
        ])
      >{{ $label }}</a>
    @endforeach
  </div>

  <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search name / email" class="min-w-[220px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Search</button>
  </form>

  <div class="p-4 pt-0">
    @if ($tab === 'onboarding')
      <x-ui.data-table :headers="['Company', 'Email', 'Service', 'Status', 'Received', '']" :paginator="$rows">
        @forelse ($rows as $row)
          <tr>
            <td class="p-3">
              <div class="font-semibold">{{ $row->company_name ?: '—' }}</div>
              <div class="text-xs text-text-subtle">{{ $row->reference ?: '—' }}</div>
            </td>
            <td class="p-3 text-sm">{{ $row->email ?: '—' }}</td>
            <td class="p-3 text-sm">{{ $row->service_label ?: '—' }}</td>
            <td class="p-3 text-sm">{{ ucfirst($row->status) }}</td>
            <td class="p-3 text-sm text-text-subtle">{{ optional($row->created_at)->diffForHumans() ?: '—' }}</td>
            <td class="p-3">
              <a href="{{ route('admin.submissions.onboarding.show', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No onboarding submissions.</td></tr>
        @endforelse
      </x-ui.data-table>
    @else
      <x-ui.data-table :headers="['Customer', 'Business', 'Document', 'Status', 'Received', '']" :paginator="$rows">
        @forelse ($rows as $row)
          <tr>
            <td class="p-3">
              <div class="font-semibold">{{ $row->customer_name ?: '—' }}</div>
              <div class="text-xs text-text-subtle">{{ $row->customer_email ?: '—' }}</div>
            </td>
            <td class="p-3 text-sm">{{ $row->business_name ?: '—' }}</td>
            <td class="p-3 text-sm">{{ $row->doc_type ?: '—' }}</td>
            <td class="p-3 text-sm">{{ ucfirst($row->status) }}</td>
            <td class="p-3 text-sm text-text-subtle">{{ optional($row->created_at)->diffForHumans() ?: '—' }}</td>
            <td class="p-3">
              <a href="{{ route('admin.submissions.readiness.show', $row) }}" class="text-xs font-semibold text-green-600 hover:underline">View</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No readiness submissions.</td></tr>
        @endforelse
      </x-ui.data-table>
    @endif
  </div>
</x-admin.layout>
