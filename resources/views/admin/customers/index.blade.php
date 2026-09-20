<x-admin.layout title="Customers - Admin" active="admin.customers.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Customers</h1>
      <p class="text-sm text-text-subtle opacity-70">Tenants / accounts on the platform.</p>
    </div>
  </div>

  <x-admin.filter-bar
    :action="route('admin.customers.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search name, email, phone, id"
    :date-from="$filters['date_from'] ?? ''"
    :date-to="$filters['date_to'] ?? ''"
    :sort="$filters['sort'] ?? 'created_at'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:filters>
      <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Status</span>
        <select name="status" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All statuses</option>
          @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <div class="p-4 pt-0">
    <x-ui.data-table
      :headers="['Customer', 'Email / Phone', 'Plan', 'Validity', 'Status', 'Created', 'Actions']"
      :paginator="$customers"
    >
      @forelse ($customers as $customer)
        @php
          $validUntil = data_get($customer->settings, 'valid_until');
          $validityDays = null;
          if (filled($validUntil)) {
              try {
                  $validityDays = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse((string) $validUntil)->startOfDay(), false);
              } catch (\Throwable) {
                  $validityDays = null;
              }
          }
        @endphp
        <tr class="bg-elevated">
          <td class="fd-table-cell p-2 align-middle">
            <a href="{{ route('admin.customers.show', $customer) }}" class="fd-table-name hover:text-green-500">
              {{ $customer->company_name ?: $customer->name }}
            </a>
            <div class="text-xs text-text-subtle">{{ $customer->id }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">
            <div>{{ $customer->email ?: '—' }}</div>
            <div>{{ $customer->phone ?: '—' }}</div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $customer->plan?->name ?: '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">
            @if ($validityDays === null)
              <span class="text-text-subtle">—</span>
            @elseif ($validityDays <= 0)
              <span class="font-semibold text-red-600">0 days</span>
              <div class="text-xs text-text-subtle">Ended {{ $validUntil }}</div>
            @elseif ($validityDays <= 10)
              <span class="font-semibold text-red-600">{{ $validityDays }} days</span>
              <div class="text-xs text-text-subtle">Until {{ $validUntil }}</div>
            @else
              <span class="font-semibold text-green-600">{{ $validityDays }} days</span>
              <div class="text-xs text-text-subtle">Until {{ $validUntil }}</div>
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle">
            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium">{{ $customer->status?->value }}</span>
          </td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-subtle">{{ format_ist($customer->created_at, 'd M Y') }}</td>
          <td class="w-[180px] p-2 align-middle">
            <x-admin.customer-row-actions :tenant="$customer" />
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="p-6 text-center text-sm text-text-subtle">No customers found.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </div>

  {{-- Extend validity & wallet (legacy parity) --}}
  <dialog id="extend-validity-dialog" class="w-full max-w-md rounded-2xl border border-border bg-elevated p-0 shadow-xl backdrop:bg-black/40">
    <form method="POST" id="extend-validity-form" class="flex flex-col gap-4 p-5">
      @csrf
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-text-primary">Extend validity &amp; wallet</h2>
          <p class="mt-1 text-sm text-text-subtle" id="extend-validity-customer">Customer</p>
        </div>
        <button type="button" class="rounded-lg px-2 py-1 text-sm text-text-subtle hover:bg-surface" data-extend-close>Close</button>
      </div>
      <p class="text-xs text-text-subtle">Current validity: <span id="extend-validity-until">—</span></p>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Extend validity (days)</span>
        <input type="number" name="days" min="0" max="3650" value="30" class="rounded-lg border border-border bg-surface px-3 py-2" placeholder="0 = no change">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Wallet top-up amount (₹)</span>
        <input type="number" name="wallet_amount" min="0" step="0.01" value="0" class="rounded-lg border border-border bg-surface px-3 py-2" placeholder="0 = no change">
      </label>
      <div class="flex justify-end gap-2">
        <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface" data-extend-close>Cancel</button>
        <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600">Save</button>
      </div>
    </form>
  </dialog>

  <script>
    (function () {
      const dialog = document.getElementById('extend-validity-dialog');
      const form = document.getElementById('extend-validity-form');
      if (!dialog || !form) return;

      document.querySelectorAll('[data-extend-validity]').forEach((btn) => {
        btn.addEventListener('click', () => {
          form.action = btn.getAttribute('data-extend-url') || '';
          document.getElementById('extend-validity-customer').textContent = btn.getAttribute('data-tenant-name') || 'Customer';
          document.getElementById('extend-validity-until').textContent = btn.getAttribute('data-valid-until') || '—';
          if (typeof dialog.showModal === 'function') dialog.showModal();
        });
      });

      document.querySelectorAll('[data-extend-close]').forEach((btn) => {
        btn.addEventListener('click', () => dialog.close());
      });

      // Close other open details menus when opening one
      document.querySelectorAll('details').forEach((d) => {
        d.addEventListener('toggle', () => {
          if (!d.open) return;
          document.querySelectorAll('details').forEach((other) => {
            if (other !== d) other.open = false;
          });
        });
      });
    })();
  </script>
</x-admin.layout>
