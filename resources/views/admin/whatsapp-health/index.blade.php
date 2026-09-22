<x-admin.layout title="WhatsApp Health - Admin" active="admin.whatsapp-health.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">WhatsApp Health</h1>
      <p class="text-sm text-text-subtle opacity-70">Fleet quality, tier pressure alerts, and daily digest (legacy parity).</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.whatsapp-health.digest') }}">
        @csrf
        <button class="rounded-lg border border-green-500 px-3 py-2 text-xs font-semibold text-green-500">Send digest now</button>
      </form>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
  @endif

  <div class="flex gap-2 px-4">
    <a href="{{ route('admin.whatsapp-health.index', ['tab' => 'fleet'] + request()->except('tab', 'page')) }}"
       @class(['rounded-lg px-3 py-2 text-sm font-semibold', 'bg-green-500 text-white' => ($tab ?? 'fleet') === 'fleet', 'bg-elevated text-text-subtle' => ($tab ?? 'fleet') !== 'fleet'])>
      Fleet
    </a>
    <a href="{{ route('admin.whatsapp-health.index', ['tab' => 'alerts']) }}"
       @class(['rounded-lg px-3 py-2 text-sm font-semibold', 'bg-green-500 text-white' => ($tab ?? '') === 'alerts', 'bg-elevated text-text-subtle' => ($tab ?? '') !== 'alerts'])>
      Alerts @if(($unreadAlerts ?? 0) > 0)<span class="ml-1 rounded bg-red-500 px-1.5 text-[10px] text-white">{{ $unreadAlerts }}</span>@endif
    </a>
  </div>

  @if (($tab ?? 'fleet') === 'alerts')
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
      <form method="GET" class="flex flex-wrap gap-2">
        <input type="hidden" name="tab" value="alerts">
        <select name="severity" class="rounded-lg border border-border px-3 py-2 text-sm">
          <option value="">Any severity</option>
          <option value="critical" @selected(request('severity') === 'critical')>Critical</option>
          <option value="warning" @selected(request('severity') === 'warning')>Warning</option>
        </select>
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="unread_only" value="1" @checked(request()->boolean('unread_only'))>
          Unread only
        </label>
        <button class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Filter</button>
      </form>
      <form method="POST" action="{{ route('admin.whatsapp-health.alerts.read-all') }}">
        @csrf
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold">Mark all read</button>
      </form>
    </div>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Severity', 'Type', 'Title', 'Tenant', 'When', 'Actions']" :paginator="$alerts">
        @forelse ($alerts ?? [] as $alert)
          <tr class="bg-elevated {{ $alert->is_read ? '' : 'bg-amber-50/40' }}">
            <td class="fd-table-cell p-2 align-middle">
              <span @class([
                'rounded px-2 py-0.5 text-xs font-semibold',
                'bg-red-50 text-red-600' => $alert->severity === 'critical',
                'bg-amber-50 text-amber-700' => $alert->severity !== 'critical',
              ])>{{ $alert->severity }}</span>
            </td>
            <td class="fd-table-cell p-2 align-middle text-xs">{{ $alert->alert_type }}</td>
            <td class="fd-table-cell p-2 align-middle">
              <div class="fd-table-name">{{ $alert->title }}</div>
              <div class="text-xs text-text-subtle">{{ \Illuminate\Support\Str::limit($alert->body, 100) }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle text-xs">{{ $alert->tenant_id ?: '—' }}</td>
            <td class="fd-table-cell p-2 align-middle text-xs">{{ format_ist($alert->occurred_at, 'd M Y H:i') }}</td>
            <td class="p-2 align-middle">
              @unless ($alert->is_read)
                <form method="POST" action="{{ route('admin.whatsapp-health.alerts.read', $alert) }}">
                  @csrf
                  <button class="text-xs font-semibold text-green-500">Mark read</button>
                </form>
              @else
                <span class="text-xs text-text-muted">Read</span>
              @endunless
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No health alerts yet.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @else
    <section class="grid gap-3 p-4 pt-4 sm:grid-cols-2 xl:grid-cols-5">
      @foreach ([
        ['Lines', $kpi['lines']],
        ['Green', $kpi['green']],
        ['Yellow', $kpi['yellow']],
        ['Red', $kpi['red']],
        ['Failed msgs', $kpi['failed_messages']],
      ] as [$label, $value])
        <div class="rounded-[20px] border border-border bg-elevated p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-text-subtle">{{ $label }}</p>
          <p class="mt-1 text-2xl font-bold text-text-primary">{{ $value }}</p>
        </div>
      @endforeach
    </section>

    <form method="GET" class="mx-4 mb-4 flex flex-wrap gap-3 rounded-[20px] border border-border bg-elevated p-4">
      <input type="hidden" name="tab" value="fleet">
      <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search phone / customer" class="min-w-[180px] flex-1 rounded-lg border border-border px-3 py-2 text-sm">
      <select name="tenant" class="rounded-lg border border-border px-3 py-2 text-sm">
        <option value="">All customers</option>
        @foreach ($tenants as $tenant)
          <option value="{{ $tenant->id }}" @selected($filters['tenant'] === $tenant->id)>{{ $tenant->company_name ?: $tenant->name }}</option>
        @endforeach
      </select>
      <select name="quality" class="rounded-lg border border-border px-3 py-2 text-sm">
        <option value="">Any quality</option>
        @foreach (['GREEN', 'YELLOW', 'RED', 'UNKNOWN'] as $q)
          <option value="{{ $q }}" @selected($filters['quality'] === $q)>{{ $q }}</option>
        @endforeach
      </select>
      <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="p-4 pt-0">
      <x-ui.data-table :headers="['Customer', 'Line', 'Quality', 'Tier', 'Delivered', 'Read', 'Failed']" :paginator="$items">
        @forelse ($items as $row)
          <tr class="bg-elevated">
            <td class="fd-table-cell p-2 align-middle">
              <a href="{{ route('admin.customers.show', $row['tenant_id']) }}" class="fd-table-name hover:text-green-500">{{ $row['tenant_name'] }}</a>
            </td>
            <td class="fd-table-cell p-2 align-middle text-sm">
              <div>{{ $row['display_name'] ?: '—' }}</div>
              <div class="text-xs text-text-subtle">{{ $row['phone'] }}</div>
            </td>
            <td class="fd-table-cell p-2 align-middle"><x-admin.status-badge :status="$row['quality_rating']" /></td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['messaging_limit_tier'] }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['delivered'] }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['read'] }}</td>
            <td class="fd-table-cell p-2 align-middle text-sm">{{ $row['failed'] }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No WhatsApp lines found.</td></tr>
        @endforelse
      </x-ui.data-table>
    </div>
  @endif
</x-admin.layout>
