<x-admin.layout title="Recent Campaigns - Admin" active="admin.recent-campaigns.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Recent campaigns</h1>
    <p class="text-sm text-text-subtle opacity-70">See which customers sent campaigns recently, with delivery stats across tenants.</p>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
      {{ session('status') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mx-4 mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
      {{ $errors->first() }}
    </div>
  @endif

  <div class="mx-4 mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Shown</div>
      <div class="mt-1 text-2xl font-bold">{{ $kpi['total'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Sending</div>
      <div class="mt-1 text-2xl font-bold text-amber-600">{{ $kpi['sending'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Completed</div>
      <div class="mt-1 text-2xl font-bold text-green-600">{{ $kpi['completed'] ?? 0 }}</div>
    </div>
    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-xs uppercase tracking-wide text-text-subtle">Failed</div>
      <div class="mt-1 text-2xl font-bold text-red-600">{{ $kpi['failed'] ?? 0 }}</div>
    </div>
  </div>

  @php
    $sortComposite = ($filters['sort'] ?? 'activity_at').':'.($filters['direction'] ?? 'desc');
  @endphp

  <form
    method="GET"
    action="{{ route('admin.recent-campaigns.index') }}"
    class="mx-4 mb-4 rounded-[20px] border border-border bg-elevated p-4"
    data-listing-toolbar
  >
    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'activity_at' }}" data-listing-sort-field>
    <input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}" data-listing-sort-direction>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Sort by</span>
        <select
          data-listing-sort
          data-listing-sort-current="{{ $sortComposite }}"
          class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
          aria-label="Sort by"
        >
          @foreach ($sortOptions as $option)
            @php
              $composite = ($option['value'] ?? '').':'.($option['direction'] ?? 'desc');
            @endphp
            <option value="{{ $composite }}" @selected($sortComposite === $composite)>{{ $option['label'] ?? $option['value'] }}</option>
          @endforeach
        </select>
      </label>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Customer</span>
        <select name="tenant" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All customers</option>
          @foreach ($tenants as $tenant)
            <option value="{{ $tenant->id }}" @selected(($filters['tenant'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
          @endforeach
        </select>
      </label>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All statuses</option>
          @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
          @endforeach
        </select>
      </label>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">From (IST)</span>
        <input
          type="date"
          name="date_from"
          value="{{ $filters['date_from'] ?? '' }}"
          class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
      </label>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">To (IST)</span>
        <input
          type="date"
          name="date_to"
          value="{{ $filters['date_to'] ?? '' }}"
          class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
      </label>

      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Search</span>
        <div class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2">
          <img src="{{ asset('images/automation/search.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
          <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="Customer / campaign / template / phone"
            data-listing-search-enter
            data-listing-search-debounce="350"
            autocomplete="off"
            class="min-w-0 flex-1 bg-transparent text-sm text-text-primary placeholder:text-text-subtle focus:outline-none"
          >
        </div>
      </label>
    </div>

    <div class="mt-3 flex justify-end">
      <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">
        Apply
      </button>
    </div>
  </form>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['Customer', 'Campaign', 'Status', 'Template / Line', 'Recipients', 'Delivered', 'Failed', 'Read', 'Activity', 'Actions']"
      :paginator="$items"
    >
      @forelse ($items as $row)
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">
              {{ $row['tenant_name'] }}
            </a>
            @if (! empty($row['created_by']))
              <div class="text-xs text-text-subtle">by {{ $row['created_by'] }}</div>
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <div class="fd-table-name">{{ $row['name'] }}</div>
            <div class="text-xs text-text-subtle">{{ $row['audience'] }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <x-admin.status-badge :status="$row['status']" />
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div>{{ $row['template_name'] }}</div>
            <div class="text-xs text-text-subtle">
              {{ $row['line_name'] ? $row['line_name'].' · ' : '' }}{{ $row['line_phone'] }}
            </div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ number_format($row['total_recipients']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-green-700">{{ number_format($row['total_delivered']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-red-700">{{ number_format($row['total_failed']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ number_format($row['total_read']) }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">
            @if (! empty($row['activity_at']))
              {{ format_ist(\Illuminate\Support\Carbon::parse($row['activity_at'])) }}
            @else
              -
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <div class="flex flex-wrap items-center gap-2">
              @if (! empty($row['can_pause']))
                <form method="POST" action="{{ route('admin.recent-campaigns.toggle', [$row['tenant_id'], $row['campaign_id']]) }}">
                  @csrf
                  @method('PATCH')
                  <button
                    type="submit"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                    title="Pause campaign"
                  >
                    Pause
                  </button>
                </form>
              @endif

              @if (! empty($row['can_resume']))
                <form method="POST" action="{{ route('admin.recent-campaigns.toggle', [$row['tenant_id'], $row['campaign_id']]) }}">
                  @csrf
                  @method('PATCH')
                  <button
                    type="submit"
                    class="rounded-lg border border-teal-300 bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 hover:bg-teal-100"
                    title="Resume campaign"
                  >
                    Resume
                  </button>
                </form>
              @endif

              <form
                method="POST"
                action="{{ route('admin.recent-campaigns.destroy', [$row['tenant_id'], $row['campaign_id']]) }}"
                data-confirm="Delete campaign “{{ $row['name'] }}” for {{ $row['tenant_name'] }}? This cannot be undone."
                data-confirm-title="Delete campaign"
                data-confirm-label="Delete"
                data-confirm-variant="danger"
              >
                @csrf
                @method('DELETE')
                <button
                  type="submit"
                  class="inline-flex size-8 items-center justify-center rounded-lg border border-border hover:bg-red-50"
                  title="Delete campaign"
                  aria-label="Delete campaign"
                >
                  <img src="{{ asset('images/campaigns/trash.svg') }}" alt="" class="size-4" width="16" height="16">
                </button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="p-6 text-center text-sm text-text-subtle">No recent campaigns found.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </div>
</x-admin.layout>
