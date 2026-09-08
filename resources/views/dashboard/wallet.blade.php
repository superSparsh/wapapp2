@php
  $period = $period ?? 'all';
  $periodLabels = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    'all' => 'All time',
  ];
@endphp

<x-layouts.app title="Wallet History - WapApp" active="dashboard">
  <div class="flex flex-col">
    <div class="flex flex-col gap-4 p-4">
      <x-dashboard.page-header
        title="Wallet History"
        subtitle="Recent wallet credits and withdrawals."
      />

      <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('dashboard.wallet') }}" class="flex w-full max-w-[550px] items-center gap-3 rounded-lg bg-elevated p-3">
          <input type="hidden" name="period" value="{{ $period }}">
          <img src="{{ asset('images/templates/search.svg') }}" alt="" class="size-5 shrink-0 opacity-60" width="20" height="20">
          <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search by description or payment ID"
            class="w-full border-0 bg-transparent text-sm text-text-primary outline-none placeholder:text-text-muted"
          >
        </form>
        <div class="flex flex-wrap items-center gap-3">
          <form method="GET" action="{{ route('dashboard.wallet') }}" class="shrink-0">
            @if (filled($search))
              <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <label class="sr-only" for="wallet_period">Sort by period</label>
            <select
              id="wallet_period"
              name="period"
              data-native-select="true"
              onchange="this.form.submit()"
              class="min-w-[10.5rem] rounded-lg border border-border bg-elevated py-2.5 pl-3 pr-8 text-sm font-medium text-text-primary outline-none focus:border-green-500"
            >
              @foreach ($periodLabels as $value => $label)
                <option value="{{ $value }}" @selected($period === $value)>Sort by : {{ $label }}</option>
              @endforeach
            </select>
          </form>
          <a
            href="{{ route('dashboard', ['modal' => 'recharge']) }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-primary-2"
          >
            <img src="{{ asset('images/icons/add-linear.svg') }}" alt="" class="size-5" width="20" height="20">
            Add Credits
          </a>
        </div>
      </div>
    </div>

    @if (session('status'))
      <div class="mx-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">{{ session('status') }}</div>
    @endif

    <section class="bg-surface px-4 pb-4">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="fd-table-head w-[54px] p-2">SI. No</th>
                <th class="fd-table-head min-w-[320px] p-2">Description</th>
                <th class="fd-table-head w-[120px] p-2">Type</th>
                <th class="fd-table-head w-[180px] p-2">Date</th>
                <th class="fd-table-head w-[140px] p-2">Amount</th>
                <th class="fd-table-head w-[140px] p-2">Balance After</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($transactions as $index => $transaction)
                @php
                  $type = $transaction->type;
                  $isCredit = $type?->value === 'credit';
                  $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
                  $description = $transaction->description
                    ?: ($meta['legacy_category'] ?? null)
                    ?: ($meta['legacy_type'] ?? null)
                    ?: ($isCredit ? 'Wallet credit' : 'Wallet withdrawal');
                  $balanceAfter = abs((float) $transaction->balance_after);
                  $detailPayload = [
                    'description' => $description,
                    'type' => $type?->label() ?? '—',
                    'amount' => ($type?->signPrefix() ?? '').'₹ '.number_format((float) $transaction->amount, 2),
                    'balance_after' => '₹ '.number_format($balanceAfter, 2),
                    'date' => $transaction->created_at?->format('d M Y h:i A') ?? '—',
                    'legacy_msg_id' => filled($meta['legacy_msg_id'] ?? null) ? (string) $meta['legacy_msg_id'] : '—',
                    'legacy_category' => filled($meta['legacy_category'] ?? null) ? (string) $meta['legacy_category'] : '—',
                    'legacy_campaign_id' => filled($meta['legacy_campaign_id'] ?? null) ? (string) $meta['legacy_campaign_id'] : '—',
                    'legacy_sender_name' => filled($meta['legacy_sender_name'] ?? null) ? (string) $meta['legacy_sender_name'] : '—',
                  ];
                @endphp
                <tr
                  class="cursor-pointer border-t border-divider bg-elevated transition hover:bg-surface"
                  data-wallet-row
                  data-wallet-detail='@json($detailPayload)'
                  role="button"
                  tabindex="0"
                >
                  <td class="fd-table-cell p-2 pl-4">{{ str_pad((string) ($transactions->firstItem() + $index), 2, '0', STR_PAD_LEFT) }}</td>
                  <td class="p-2">
                    <p class="fd-table-name text-text-subtle">{{ $description }}</p>
                  </td>
                  <td class="fd-table-cell p-2">
                    <span @class([
                      'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                      'bg-green-50 text-green-600' => $isCredit,
                      'bg-red-50 text-red-600' => ! $isCredit,
                    ])>{{ $type?->label() ?? '—' }}</span>
                  </td>
                  <td class="fd-table-cell p-2">{{ $transaction->created_at?->format('d M Y h:i A') }}</td>
                  <td @class([
                    'fd-table-cell p-2 font-semibold',
                    'text-green-600' => $isCredit,
                    'text-red-600' => ! $isCredit,
                  ])>
                    {{ $type?->signPrefix() ?? '' }}₹ {{ number_format((float) $transaction->amount, 2) }}
                  </td>
                  <td class="fd-table-cell p-2">₹ {{ number_format($balanceAfter, 2) }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-6 text-center text-sm text-text-muted">No wallet transactions found for this period.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if ($transactions->hasPages())
          <x-ui.table-pagination :paginator="$transactions" />
        @endif
      </div>
    </section>
  </div>

  <div id="wallet-detail-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 p-4" aria-hidden="true">
    <div class="w-full max-w-lg rounded-2xl border border-border bg-elevated shadow-xl" role="dialog" aria-modal="true" aria-labelledby="wallet-detail-title">
      <div class="flex items-center justify-between border-b border-divider px-5 py-4">
        <h2 id="wallet-detail-title" class="text-lg font-semibold text-text-primary">Transaction details</h2>
        <button type="button" data-wallet-detail-close class="rounded-lg px-2 py-1 text-lg leading-none text-text-muted hover:bg-surface" aria-label="Close">×</button>
      </div>
      <div class="space-y-3 px-5 py-4 text-sm text-text-body">
        <div class="flex justify-between gap-4"><span class="text-text-muted">Description</span><span data-detail-description class="text-right font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Type</span><span data-detail-type class="font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Amount</span><span data-detail-amount class="font-semibold text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Balance after</span><span data-detail-balance class="font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Date</span><span data-detail-date class="font-medium text-text-primary"></span></div>
        <div class="my-1 h-px w-full bg-divider"></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Msg ID</span><span data-detail-msg-id class="text-right font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Category</span><span data-detail-category class="text-right font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Campaign ID</span><span data-detail-campaign-id class="text-right font-medium text-text-primary"></span></div>
        <div class="flex justify-between gap-4"><span class="text-text-muted">Sender</span><span data-detail-sender class="text-right font-medium text-text-primary"></span></div>
      </div>
      <div class="border-t border-divider px-5 py-3 text-right">
        <button type="button" data-wallet-detail-close class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Close</button>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const modal = document.getElementById('wallet-detail-modal');
      if (!modal) return;

      const fill = (selector, value) => {
        const el = modal.querySelector(selector);
        if (el) el.textContent = value || '—';
      };

      const open = (data) => {
        fill('[data-detail-description]', data.description);
        fill('[data-detail-type]', data.type);
        fill('[data-detail-amount]', data.amount);
        fill('[data-detail-balance]', data.balance_after);
        fill('[data-detail-date]', data.date);
        fill('[data-detail-msg-id]', data.legacy_msg_id);
        fill('[data-detail-category]', data.legacy_category);
        fill('[data-detail-campaign-id]', data.legacy_campaign_id);
        fill('[data-detail-sender]', data.legacy_sender_name);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
      };

      const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
      };

      document.querySelectorAll('[data-wallet-row]').forEach((row) => {
        const show = () => {
          try {
            open(JSON.parse(row.dataset.walletDetail || '{}'));
          } catch (e) {}
        };
        row.addEventListener('click', show);
        row.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            show();
          }
        });
      });

      modal.querySelectorAll('[data-wallet-detail-close]').forEach((btn) => btn.addEventListener('click', close));
      modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
      });
    })();
  </script>
</x-layouts.app>
