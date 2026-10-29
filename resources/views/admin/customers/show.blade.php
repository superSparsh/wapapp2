<x-admin.layout title="{{ ($tenant->company_name ?: $tenant->name) }} - Admin" active="admin.customers.index">
  @php
    $displayName = $tenant->company_name ?: $tenant->name;
    $walletBalance = $wallet['balance'] ?? null;
    $walletCurrency = $wallet['currency'] ?? 'INR';
    $walletLow = $walletBalance !== null && $walletBalance <= 500;
    $daysLeft = $days_left;
    $validityTone = match (true) {
      $daysLeft === null => 'muted',
      $daysLeft <= 0 => 'danger',
      $daysLeft <= 10 => 'warn',
      default => 'ok',
    };
    $walletDisplay = strtoupper((string) data_get($settings, 'wallet_display_currency', config('services.wallet_display_currency_default', 'INR')));
  @endphp

  {{-- Header --}}
  <div class="flex flex-wrap items-start justify-between gap-4 p-4">
    <div class="min-w-0">
      <a href="{{ route('admin.customers.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Customers</a>
      <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="truncate text-2xl font-bold text-text-primary">{{ $displayName }}</h1>
        <x-admin.status-badge :status="$tenant->status" />
      </div>
      <p class="mt-1 text-sm text-text-subtle">
        {{ $tenant->email ?: '-' }}
        @if ($tenant->phone)
          · {{ $tenant->phone }}
        @endif
        · <span class="font-mono text-xs">{{ $tenant->id }}</span>
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('admin.customers.edit', $tenant) }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Edit profile</a>
      <form method="POST" action="{{ route('admin.customers.toggle-status', $tenant) }}">
        @csrf
        <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">
          {{ $tenant->status?->value === 'active' ? 'Suspend' : 'Activate' }}
        </button>
      </form>
      <form method="POST" action="{{ route('admin.customers.login-as', $tenant) }}">
        @csrf
        <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white hover:bg-green-600">Login as customer</button>
      </form>
    </div>
  </div>

  {{-- KPI strip --}}
  <section class="grid gap-3 px-4 pb-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-[20px] border border-border bg-elevated p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Wallet</p>
      <p @class(['mt-2 text-2xl font-bold tabular-nums', 'text-red-600' => $walletLow, 'text-text-primary' => ! $walletLow])>
        @if ($walletBalance === null)
          -
        @else
          {{ $walletCurrency === 'USD' ? '$' : '₹' }}{{ number_format($walletBalance, 2) }}
        @endif
      </p>
      <p class="mt-1 text-xs text-text-subtle">{{ $walletLow ? 'Low balance' : 'Available credits' }} · view as {{ $walletDisplay }}</p>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Validity</p>
      <p @class([
        'mt-2 text-2xl font-bold tabular-nums',
        'text-red-600' => $validityTone === 'danger' || $validityTone === 'warn',
        'text-text-primary' => $validityTone === 'ok' || $validityTone === 'muted',
      ])>
        @if ($daysLeft === null)
          -
        @elseif ($daysLeft < 0)
          Expired
        @else
          {{ $daysLeft }}d
        @endif
      </p>
      <p class="mt-1 text-xs text-text-subtle">
        {{ $valid_until?->toFormattedDateString() ?: 'No end date set' }}
        @if ($tenant->plan?->name)
          · {{ $tenant->plan->name }}
        @endif
      </p>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">WhatsApp lines</p>
      <p class="mt-2 text-2xl font-bold tabular-nums text-text-primary">
        {{ $usage['lines_connected'] ?? 0 }}<span class="text-base font-semibold text-text-subtle"> / {{ $usage['lines_total'] ?? 0 }}</span>
      </p>
      <p class="mt-1 text-xs text-text-subtle">Connected / total</p>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Messages (7d)</p>
      <p class="mt-2 text-2xl font-bold tabular-nums text-text-primary">
        {{ number_format(($usage['outbound_7d'] ?? 0) + ($usage['inbound_7d'] ?? 0)) }}
      </p>
      <p class="mt-1 text-xs text-text-subtle">
        ↑ {{ number_format($usage['outbound_7d'] ?? 0) }} out
        · ↓ {{ number_format($usage['inbound_7d'] ?? 0) }} in
        @if (($usage['failed_7d'] ?? 0) > 0)
          · <span class="text-red-600">{{ number_format($usage['failed_7d']) }} failed</span>
        @endif
      </p>
    </div>
  </section>

  {{-- Quick links --}}
  <nav class="flex flex-wrap gap-2 px-4 pb-4">
    <a href="{{ route('admin.customers.activity-logs', $tenant) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Activity logs</a>
    <a href="{{ route('admin.billing-audit.index', ['tenant' => $tenant->id]) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Billing audit</a>
    <a href="{{ route('admin.whatsapp-health.index', ['tenant' => $tenant->id]) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">WhatsApp health</a>
    <a href="{{ route('admin.message-performance.index', ['tenant' => $tenant->id]) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Message performance</a>
    <a href="{{ route('admin.retention.show', $tenant) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Retention</a>
    <a href="{{ route('admin.wallet-recharges.index', ['tenant' => $tenant->id]) }}" class="rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Wallet recharges</a>
  </nav>

  <section class="grid gap-4 px-4 pb-4 xl:grid-cols-3">
    {{-- WhatsApp lines --}}
    <div class="rounded-[20px] border border-border bg-elevated p-5 xl:col-span-2">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-lg font-bold text-text-primary">WhatsApp lines</h2>
        <a href="{{ route('admin.whatsapp-health.index', ['tenant' => $tenant->id]) }}" class="text-xs font-semibold text-green-600 hover:underline">Open health →</a>
      </div>

      <div class="mt-4 divide-y divide-border">
        @forelse ($lines as $line)
          <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-text-primary">
                {{ $line['display_name'] ?: $line['phone'] }}
                @if ($line['is_default'])
                  <span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-green-700">Default</span>
                @endif
              </p>
              <p class="mt-0.5 font-mono text-xs text-text-subtle">{{ $line['phone'] }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              @php
                $q = $line['quality'];
                $qClass = match ($q) {
                  'GREEN' => 'bg-green-100 text-green-700',
                  'YELLOW' => 'bg-amber-100 text-amber-800',
                  'RED' => 'bg-red-100 text-red-700',
                  default => 'bg-surface text-text-subtle',
                };
              @endphp
              <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $qClass }}">{{ $q }}</span>
              <span class="rounded-full border border-border px-2 py-0.5 text-[10px] font-semibold text-text-subtle">{{ $line['tier'] }}</span>
              <span @class([
                'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                'bg-green-100 text-green-700' => $line['connected'],
                'bg-surface text-text-subtle' => ! $line['connected'],
              ])>{{ $line['connected'] ? 'Connected' : 'Not linked' }}</span>
            </div>
          </div>
        @empty
          <p class="py-6 text-sm text-text-subtle">No WhatsApp lines on this account yet.</p>
        @endforelse
      </div>
    </div>

    {{-- Account + subscription --}}
    <div class="flex flex-col gap-4">
      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Subscription</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Plan</dt>
            <dd class="font-semibold text-text-primary">{{ $tenant->plan?->name ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Status</dt>
            <dd class="font-semibold text-text-primary">{{ $subscription['status'] ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Starts</dt>
            <dd class="font-semibold text-text-primary">{{ $subscription['starts_at'] ? \Illuminate\Support\Carbon::parse($subscription['starts_at'])->toFormattedDateString() : '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Ends</dt>
            <dd class="font-semibold text-text-primary">{{ $subscription['ends_at'] ? \Illuminate\Support\Carbon::parse($subscription['ends_at'])->toFormattedDateString() : ($valid_until?->toFormattedDateString() ?: '-') }}</dd>
          </div>
          @if ($subscription['amount'])
            <div class="flex justify-between gap-3">
              <dt class="text-text-subtle">Amount</dt>
              <dd class="font-semibold text-text-primary">{{ $subscription['currency'] }} {{ $subscription['amount'] }}</dd>
            </div>
          @endif
        </dl>
      </div>

      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Account</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Name</dt>
            <dd class="text-right font-semibold text-text-primary">{{ $tenant->name ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Company</dt>
            <dd class="text-right font-semibold text-text-primary">{{ $tenant->company_name ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Timezone</dt>
            <dd class="font-semibold text-text-primary">{{ $tenant->timezone ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Created</dt>
            <dd class="font-semibold text-text-primary">{{ optional($tenant->created_at)->toFormattedDateString() ?: '-' }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-text-subtle">Provisioned</dt>
            <dd class="font-semibold text-text-primary">{{ optional($tenant->provisioned_at)->toFormattedDateString() ?: '-' }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </section>

  {{-- Usage inventory --}}
  <section class="grid gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['Contacts', number_format($usage['contacts'] ?? 0)],
      ['Campaigns', number_format($usage['campaigns'] ?? 0)],
      ['Templates', number_format($usage['templates'] ?? 0)],
      ['Login seats', number_format($access_rows->count())],
    ] as [$label, $value])
      <div class="rounded-[16px] border border-border bg-elevated px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">{{ $label }}</p>
        <p class="mt-1 text-xl font-bold tabular-nums text-text-primary">{{ $value }}</p>
      </div>
    @endforeach
  </section>

  <section class="grid gap-4 px-4 pb-4 xl:grid-cols-2">
    {{-- Recent wallet --}}
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-lg font-bold text-text-primary">Recent wallet activity</h2>
        <a href="{{ route('admin.billing-audit.index', ['tenant' => $tenant->id, 'type' => 'wallet']) }}" class="text-xs font-semibold text-green-600 hover:underline">Full ledger →</a>
      </div>
      <div class="mt-4 divide-y divide-border">
        @forelse ($recent_wallet as $tx)
          <div class="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-text-primary">{{ $tx['description'] }}</p>
              <p class="mt-0.5 text-xs text-text-subtle">{{ $tx['type'] }} · {{ $tx['created_at'] ? \Illuminate\Support\Carbon::parse($tx['created_at'])->diffForHumans() : '' }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <p class="text-sm font-bold tabular-nums text-text-primary">{{ $tx['amount'] }}</p>
              @if (! empty($tx['uuid']))
                <form method="POST" action="{{ route('admin.customers.wallet-transactions.destroy', [$tenant, $tx['uuid']]) }}" onsubmit="return confirm('Delete this wallet activity and reverse its balance effect?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="rounded-md border border-red-200 px-2 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50">Delete</button>
                </form>
              @endif
            </div>
          </div>
        @empty
          <p class="py-4 text-sm text-text-subtle">No wallet transactions yet.</p>
        @endforelse
      </div>
    </div>

    {{-- Login access --}}
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Login access</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="text-xs uppercase text-text-subtle">
              <th class="py-2 pr-3">Email</th>
              <th class="py-2 pr-3">Phone</th>
              <th class="py-2 pr-3">Type</th>
              <th class="py-2">Active</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            @forelse ($access_rows as $row)
              <tr>
                <td class="py-2 pr-3">{{ $row->email }}</td>
                <td class="py-2 pr-3">{{ $row->phone ?: '-' }}</td>
                <td class="py-2 pr-3">{{ $row->account_type?->value }}</td>
                <td class="py-2"><x-admin.status-badge :status="$row->is_active" /></td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-4 text-text-subtle">No access rows.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  {{-- Custom customer terms (business-authored) --}}
  <section class="px-4 pb-4" id="custom-terms">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-text-primary">Custom customer terms</h2>
          <p class="mt-1 text-xs text-text-subtle">Business-authored terms for their end customers. Reject or remove non-compliant wording; optionally suspend the account.</p>
        </div>
        @if ($custom_terms)
          <span class="rounded-lg bg-surface px-3 py-1.5 text-xs font-semibold text-text-primary">
            {{ $custom_terms->status?->label() ?? $custom_terms->status }} · v{{ $custom_terms->version }}
          </span>
        @endif
      </div>

      @if (! $custom_terms)
        <p class="mt-4 text-sm text-text-subtle">This business has not created custom terms yet.</p>
      @else
        <div class="mt-4 rounded-xl border border-border bg-surface p-4">
          <p class="text-sm font-semibold text-text-primary">{{ $custom_terms->title }}</p>
          <div class="mt-3 max-h-64 overflow-y-auto whitespace-pre-wrap text-sm leading-[1.6] text-text-body">{{ $custom_terms->body }}</div>
          @if ($custom_terms->admin_note)
            <p class="mt-3 text-xs text-text-muted"><span class="font-semibold">Last admin note:</span> {{ $custom_terms->admin_note }}</p>
          @endif
          @if ($custom_terms->submitted_at)
            <p class="mt-1 text-xs text-text-muted">Submitted {{ $custom_terms->submitted_at->toDayDateTimeString() }}</p>
          @endif
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
          <form method="POST" action="{{ route('admin.customers.custom-terms.approve', $tenant) }}" class="flex flex-col gap-2 rounded-xl border border-border p-3">
            @csrf
            <p class="text-xs font-semibold text-text-primary">Approve</p>
            <input type="text" name="admin_note" maxlength="1000" placeholder="Optional note" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Approve &amp; publish</button>
          </form>

          <form method="POST" action="{{ route('admin.customers.custom-terms.reject', $tenant) }}" class="flex flex-col gap-2 rounded-xl border border-border p-3">
            @csrf
            <p class="text-xs font-semibold text-text-primary">Reject</p>
            <textarea name="admin_note" required rows="3" maxlength="2000" placeholder="Why these terms are wrong (required)" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm"></textarea>
            <label class="flex items-center gap-2 text-xs font-semibold text-text-primary">
              <input type="checkbox" name="suspend" value="1" class="rounded border-border">
              Also suspend account
            </label>
            <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Reject &amp; notify</button>
          </form>

          <form method="POST" action="{{ route('admin.customers.custom-terms.remove', $tenant) }}" class="flex flex-col gap-2 rounded-xl border border-border p-3" onsubmit="return confirm('Remove these custom terms? The business will be notified.');">
            @csrf
            <p class="text-xs font-semibold text-text-primary">Remove</p>
            <textarea name="admin_note" required rows="3" maxlength="2000" placeholder="Why removing (required)" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm"></textarea>
            <label class="flex items-center gap-2 text-xs font-semibold text-text-primary">
              <input type="checkbox" name="suspend" value="1" class="rounded border-border">
              Also suspend account
            </label>
            <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">Remove &amp; notify</button>
          </form>
        </div>
      @endif
    </div>
  </section>

  {{-- Admin controls --}}
  <section class="grid gap-4 px-4 pb-8 xl:grid-cols-3" id="assign-plan">
    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Assign plan</h2>
      <form method="POST" action="{{ route('admin.customers.assign-plan', $tenant) }}" class="mt-4 flex flex-col gap-3">
        @csrf
        <select name="plan" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm">
          <option value="">No plan</option>
          @foreach ($plans as $plan)
            <option value="{{ $plan->uuid }}" @selected($tenant->plan?->uuid === $plan->uuid)>
              {{ $plan->name }} ({{ $plan->currency }} {{ $plan->price }})
            </option>
          @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Save plan</button>
      </form>
    </div>

    <div class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Extend validity &amp; wallet</h2>
      <p class="mt-1 text-xs text-text-subtle">
        Current: {{ $valid_until?->toFormattedDateString() ?: 'no date' }}
        · Wallet {{ $walletBalance === null ? '-' : (($walletCurrency === 'USD' ? '$' : '₹').number_format($walletBalance, 2)) }}
      </p>
      <form method="POST" action="{{ route('admin.customers.extend-validity', $tenant) }}" class="mt-4 flex flex-col gap-3">
        @csrf
        <input type="number" name="days" min="0" max="3650" value="30" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" placeholder="Days (0 = no change)">
        <input type="number" name="wallet_amount" min="0" step="0.01" value="0" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" placeholder="Wallet top-up ₹ (0 = no change)">
        <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Apply credit / validity</button>
      </form>
      <form method="POST" action="{{ route('admin.customers.wallet-debit', $tenant) }}" class="mt-4 flex flex-col gap-3 border-t border-border pt-4">
        @csrf
        <p class="text-xs font-semibold text-text-primary">Deduct wallet</p>
        <input type="number" name="wallet_amount" min="0.01" step="0.01" required class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" placeholder="Amount to deduct ₹">
        <input type="text" name="description" maxlength="255" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm" placeholder="Reason (optional)">
        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">Deduct from wallet</button>
      </form>
    </div>

    <div class="flex flex-col gap-4">
      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Wallet display</h2>
        <p class="mt-1 text-xs text-text-subtle">Admin “view as” currency only.</p>
        <form method="POST" action="{{ route('admin.customers.wallet-display-currency', $tenant) }}" class="mt-3 flex flex-wrap gap-2">
          @csrf
          <button type="submit" name="currency" value="INR" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $walletDisplay === 'INR' ? 'bg-green-500 text-white' : 'border border-border hover:bg-surface' }}">INR</button>
          <button type="submit" name="currency" value="USD" class="rounded-lg px-3 py-2 text-xs font-semibold {{ $walletDisplay === 'USD' ? 'bg-green-500 text-white' : 'border border-border hover:bg-surface' }}">USD</button>
        </form>
      </div>

      <div class="rounded-[20px] border border-border bg-elevated p-5">
        <h2 class="text-lg font-bold text-text-primary">Inbox settings</h2>
        <form method="POST" action="{{ route('admin.customers.settings', $tenant) }}" class="mt-3">
          @csrf
          @method('PATCH')
          <input type="hidden" name="inbox_phone_masking_enabled" value="0">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="inbox_phone_masking_enabled" value="1" class="rounded border-border" @checked((bool) data_get($settings, 'inbox_phone_masking_enabled', false))>
            <span class="font-semibold">Phone masking</span>
          </label>
          <button type="submit" class="mt-3 rounded-lg bg-green-500 px-3 py-2 text-xs font-semibold text-white">Save</button>
        </form>
      </div>
    </div>
  </section>
</x-admin.layout>
