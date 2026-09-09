<x-admin.layout title="Customer Submissions - Admin" active="admin.submissions.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Customer submissions</h1>
    <p class="text-sm text-text-subtle opacity-70">Readiness and onboarding forms received from prospects.</p>
  </div>

  <div class="mx-4 mb-4 flex flex-wrap gap-2">
    @foreach (['readiness' => 'Readiness', 'onboarding' => 'Onboarding'] as $key => $label)
      <a
        href="{{ route('admin.submissions.index', array_filter([
          'tab' => $key,
          'q' => $filters['q'] ?? null,
          'sort' => $filters['sort'] ?? null,
          'direction' => $filters['direction'] ?? null,
        ])) }}"
        @class([
          'rounded-lg px-3 py-2 text-xs font-semibold',
          'bg-green-500 text-white' => $tab === $key,
          'border border-border hover:bg-surface' => $tab !== $key,
        ])
      >{{ $label }}</a>
    @endforeach
  </div>

  <x-admin.filter-bar
    :action="route('admin.submissions.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name / email"
    :show-dates="false"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:hidden>
      <input type="hidden" name="tab" value="{{ $tab }}">
    </x-slot:hidden>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    @if ($tab === 'onboarding')
      <x-ui.data-table :headers="['Company', 'Email', 'Service', 'Status', 'Received', 'Actions']" :paginator="$rows">
        @forelse ($rows as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <div class="fd-table-name">{{ $row->company_name ?: '—' }}</div>
              <div class="text-xs text-text-subtle">{{ $row->reference ?: '—' }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->email ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->service_label ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ ucfirst($row->status) }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->created_at) }}</td>
            <td class="w-[120px] p-2 align-middle">
              <x-ui.table-actions
                :actions="['eye']"
                :links="['eye' => route('admin.submissions.onboarding.show', $row)]"
              />
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No onboarding submissions.</td></tr>
        @endforelse
      </x-ui.data-table>
    @else
      <x-ui.data-table :headers="['Customer', 'Business', 'Document', 'Status', 'Received', 'Actions']" :paginator="$rows">
        @forelse ($rows as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <div class="fd-table-name">{{ $row->customer_name ?: '—' }}</div>
              <div class="text-xs text-text-subtle">{{ $row->customer_email ?: '—' }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->business_name ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->doc_type ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ ucfirst($row->status) }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->created_at) }}</td>
            <td class="w-[120px] p-2 align-middle">
              <x-ui.table-actions
                :actions="['eye']"
                :links="['eye' => route('admin.submissions.readiness.show', $row)]"
              />
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No readiness submissions.</td></tr>
        @endforelse
      </x-ui.data-table>
    @endif
  </div>
</x-admin.layout>
