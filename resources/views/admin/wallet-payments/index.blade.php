<x-admin.layout title="Wallet payments - Admin" active="admin.wallet-payments.index">
  @php
    $tab = $tab ?? 'journeys';
  @endphp

  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Wallet payments</h1>
    <p class="text-sm text-text-subtle opacity-70">One place for Razorpay wallet top-ups, manual requests, credits, and Zoho sync.</p>
  </div>

  <div class="flex flex-wrap items-center gap-2 px-4 pb-4">
    @foreach ([
      'journeys' => 'Razorpay journeys',
      'manual' => 'Manual requests',
      'credits' => 'Wallet credits',
      'zoho' => 'Zoho credits',
    ] as $key => $label)
      <a
        href="{{ route('admin.wallet-payments.index', ['tab' => $key]) }}"
        @class([
          'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
          'bg-green-600 text-white' => $tab === $key,
          'border border-border text-text-subtle hover:bg-surface' => $tab !== $key,
        ])
      >{{ $label }}</a>
    @endforeach
  </div>

  @if ($tab === 'journeys')
    <x-admin.filter-bar
      :action="route('admin.wallet-payments.index')"
      :search="$filters['q'] ?? ''"
      search-placeholder="Search order / payment id"
      :show-dates="false"
      :sort="$filters['sort'] ?? 'created_at'"
      :direction="$filters['direction'] ?? 'desc'"
      :sort-options="$sortOptions"
    >
      <x-slot:hidden>
        <input type="hidden" name="tab" value="journeys" />
      </x-slot:hidden>
      <x-slot:filters>
        <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
          <span class="font-semibold text-text-primary">Customer</span>
          <select name="tenant" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
            <option value="">All customers</option>
            @foreach ($tenants as $tenant)
              <option value="{{ $tenant->id }}" @selected(($filters['tenant'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
            @endforeach
          </select>
        </label>
        <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
          <span class="font-semibold text-text-primary">Journey</span>
          <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
            <option value="">All</option>
            @foreach ([
              'awaiting_payment' => 'Awaiting payment',
              'paid_wallet_missing' => 'Paid · wallet missing',
              'zoho_pending' => 'Wallet OK · Zoho pending',
              'zoho_failed' => 'Wallet OK · Zoho failed',
              'complete' => 'Complete',
              'failed' => 'Failed',
            ] as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
      </x-slot:filters>
    </x-admin.filter-bar>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Customer', 'Amount', 'Journey', 'Order', 'Payment', 'When', '']" :paginator="$items">
        @forelse ($items as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['currency'] }} {{ $row['amount'] }}</td>
            <td class="fd-table-cell p-2 align-middle">
              <x-admin.status-badge :status="$row['journey_status']" :label="$row['journey_label']" />
            </td>
            <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $row['razorpay_order_id'] }}</td>
            <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $row['razorpay_payment_id'] ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row['created_at']) }}</td>
            <td class="w-[80px] p-2 align-middle">
              <x-ui.table-actions
                :actions="['eye']"
                :links="['eye' => route('admin.wallet-payments.show', [$row['tenant_id'], $row['order_uuid']])]"
              />
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No Razorpay wallet journeys found.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @elseif ($tab === 'manual')
    <x-admin.filter-bar
      :action="route('admin.wallet-payments.index')"
      :search="$filters['q'] ?? ''"
      search-placeholder="Search tenant id or notes…"
      :show-dates="false"
      :sort="$filters['sort'] ?? 'id'"
      :direction="$filters['direction'] ?? 'desc'"
      :sort-options="$sortOptions"
    >
      <x-slot:hidden>
        <input type="hidden" name="tab" value="manual" />
      </x-slot:hidden>
      <x-slot:filters>
        <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
          <span class="font-semibold text-text-primary">Status</span>
          <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
            <option value="">All statuses</option>
            @foreach (['pending', 'approved', 'rejected'] as $option)
              <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
          </select>
        </label>
      </x-slot:filters>
    </x-admin.filter-bar>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Customer', 'Amount', 'Status', 'Notes', 'Requested', 'Actions']" :paginator="$rows">
        @forelse ($rows as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <div class="fd-table-name">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
              <div class="text-xs text-text-subtle">{{ $row->tenant_id }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
            <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row->status" /></td>
            <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($row->notes, 60) ?: '-' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->created_at) }}</td>
            <td class="w-[120px] p-2 align-middle">
              @if ($row->status === 'pending')
                <x-ui.table-actions
                  :actions="['approve']"
                  :links="['approve' => route('admin.recharge-requests.approve', $row)]"
                  :methods="['approve' => 'POST']"
                  confirm="Approve and credit the customer wallet?"
                  confirm-title="Approve recharge"
                  confirm-label="Approve"
                />
              @else
                <span class="block text-center text-xs text-text-subtle">{{ format_ist($row->approved_at, 'd M Y') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No recharge requests.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @elseif ($tab === 'credits')
    <x-admin.filter-bar
      :action="route('admin.wallet-payments.index')"
      :search="$filters['q'] ?? ''"
      search-placeholder="Search description / payment id"
      :show-dates="false"
      :sort="$filters['sort'] ?? 'created_at'"
      :direction="$filters['direction'] ?? 'desc'"
      :sort-options="$sortOptions"
    >
      <x-slot:hidden>
        <input type="hidden" name="tab" value="credits" />
      </x-slot:hidden>
      <x-slot:filters>
        <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
          <span class="font-semibold text-text-primary">Customer</span>
          <select name="tenant" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
            <option value="">All customers</option>
            @foreach ($tenants as $tenant)
              <option value="{{ $tenant->id }}" @selected(($filters['tenant'] ?? '') === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
            @endforeach
          </select>
        </label>
      </x-slot:filters>
    </x-admin.filter-bar>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Customer', 'Amount', 'Description', 'Reference', 'When']" :paginator="$items">
        @forelse ($items as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row['currency'] }} {{ $row['amount'] }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['description'] ?: '-' }}</td>
            <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $row['razorpay_payment_id'] ?: '-' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row['created_at']) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No wallet credits found.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @else
    <x-admin.filter-bar
      :action="route('admin.wallet-payments.index')"
      :search="$filters['q'] ?? ''"
      search-placeholder="Search invoice / payment / tenant"
      :show-dates="false"
      :sort="$filters['sort'] ?? 'id'"
      :direction="$filters['direction'] ?? 'desc'"
      :sort-options="$sortOptions"
    >
      <x-slot:hidden>
        <input type="hidden" name="tab" value="zoho" />
      </x-slot:hidden>
      <x-slot:filters>
        <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
          <span class="font-semibold text-text-primary">Source</span>
          <select name="source" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
            <option value="">All sources</option>
            @foreach (['razorpay', 'zoho'] as $option)
              <option value="{{ $option }}" @selected($source === $option)>{{ ucfirst($option) }}</option>
            @endforeach
          </select>
        </label>
      </x-slot:filters>
    </x-admin.filter-bar>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Customer', 'Amount', 'Source', 'Status', 'Invoice', 'Received', '']" :paginator="$zohoRows">
        @forelse ($zohoRows as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <div class="fd-table-name">{{ $row->tenant?->company_name ?: ($row->tenant?->name ?: $row->tenant_id) }}</div>
              <div class="text-xs text-text-subtle">{{ $row->external_id ?: '—' }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $row->currency }} {{ $row->amount }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row->source }}</td>
            <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row->status" /></td>
            <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $row->invoice_number ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($row->created_at) }}</td>
            <td class="w-[80px] p-2 align-middle">
              <x-ui.table-actions
                :actions="['eye']"
                :links="['eye' => route('admin.zoho-credits.show', $row)]"
              />
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No Zoho credit requests.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @endif
</x-admin.layout>
