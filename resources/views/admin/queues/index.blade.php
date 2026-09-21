<x-admin.layout title="Queues - Admin" active="admin.queues.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Queues</h1>
      <p class="text-sm text-text-subtle opacity-70">
        Connection <span class="font-semibold">{{ $connection }}</span>
        ({{ $driver }}) — pending {{ $pending_count }}, failed {{ $failed_count }}
        @if ($module)
          for <span class="font-semibold">{{ $modules[$module] ?? $module }}</span>
        @endif.
      </p>
      <p class="mt-1 text-xs text-text-subtle">Retry all / Flush apply to <span class="font-semibold">all</span> failed jobs (every module).</p>
      @php
        $horizonStatus = $horizon['status'] ?? 'unknown';
        $horizonLabel = match ($horizonStatus) {
          'running' => 'Horizon running',
          'inactive' => 'Horizon inactive',
          'unavailable' => 'Horizon unavailable',
          default => 'Horizon status unknown',
        };
        $horizonTone = match ($horizonStatus) {
          'running' => 'bg-green-50 text-green-700 ring-green-200',
          'inactive' => 'bg-amber-50 text-amber-700 ring-amber-200',
          default => 'bg-surface text-text-subtle ring-border',
        };
        $redisOk = (bool) ($redis_probe['ok'] ?? false);
      @endphp
      <div class="mt-2 flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $horizonTone }}">{{ $horizonLabel }}</span>
        <span @class([
          'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
          'bg-green-50 text-green-700 ring-green-200' => $redisOk,
          'bg-red-50 text-red-700 ring-red-200' => ! $redisOk,
        ])>Redis {{ $redisOk ? 'OK' : 'down' }}</span>
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.queues.retry-all') }}">@csrf
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Retry all failed</button>
      </form>
      <form method="POST" action="{{ route('admin.queues.clear-older') }}" data-confirm="Delete failed jobs older than 30 days{{ $module ? ' for this module' : '' }}?" data-confirm-variant="danger">
        @csrf
        <input type="hidden" name="days" value="30">
        @if ($module)
          <input type="hidden" name="module" value="{{ $module }}">
        @endif
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Clear &gt;30 days</button>
      </form>
      @if ($module)
        <form method="POST" action="{{ route('admin.queues.flush-module') }}" data-confirm="Delete ALL failed jobs for {{ $modules[$module] ?? $module }}?" data-confirm-variant="danger">
          @csrf
          <input type="hidden" name="module" value="{{ $module }}">
          <button class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">Delete module failed</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.queues.flush') }}" data-confirm="Flush ALL failed jobs across every module?" data-confirm-variant="danger">@csrf
        <button class="rounded-lg bg-red-500 px-3 py-2 text-xs font-semibold text-white">Flush all failed</button>
      </form>
    </div>
  </div>

  @if (session('ops_output'))
    <div class="mx-4 mb-4 rounded-xl border border-border bg-elevated p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle">Last ops output @if (session('ops_command')) ({{ session('ops_command') }}) @endif</p>
      <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs leading-relaxed text-text-primary">{{ session('ops_output') }}</pre>
    </div>
  @endif

  <section class="space-y-4 p-4 pt-0">
    <div>
      <h2 class="text-lg font-bold text-text-primary">Server ops</h2>
      <p class="text-sm text-text-subtle">
        Allowlisted commands only (queue, Horizon, Laravel cache, Redis, Supervisor, Apache).
        Shell actions need passwordless sudo for the PHP user — see <code class="text-xs">config/admin_ops.php</code>.
      </p>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
      @foreach ($ops_groups as $group => $commands)
        <div class="rounded-xl border border-border bg-elevated p-4">
          <h3 class="text-sm font-bold text-text-primary">{{ $group }}</h3>
          <ul class="mt-3 divide-y divide-border">
            @foreach ($commands as $cmd)
              <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-text-primary">{{ $cmd['label'] }}</p>
                  <p class="mt-0.5 text-xs text-text-subtle">{{ $cmd['description'] }}</p>
                </div>
                <form method="POST" action="{{ route('admin.queues.ops') }}"
                  @if ($cmd['danger'])
                    data-confirm="Run “{{ $cmd['label'] }}”? This can affect live traffic."
                    data-confirm-variant="danger"
                  @else
                    data-confirm="Run “{{ $cmd['label'] }}”?"
                  @endif
                >
                  @csrf
                  <input type="hidden" name="command" value="{{ $cmd['key'] }}">
                  <button
                    type="submit"
                    @class([
                      'rounded-lg px-3 py-1.5 text-xs font-semibold',
                      'border border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100' => $cmd['danger'],
                      'border border-border bg-surface text-text-primary hover:bg-white' => ! $cmd['danger'],
                    ])
                  >Run</button>
                </form>
              </li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
  </section>

  <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
    <a
      href="{{ route('admin.queues.index', array_filter([
        'q' => $filters['q'] ?? null,
        'queue' => $filters['queue'] ?? null,
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'sort' => $filters['sort'] ?? null,
        'direction' => $filters['direction'] ?? null,
      ])) }}"
      @class([
        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
        'bg-green-600 text-white' => blank($module),
        'border border-border text-text-subtle hover:bg-surface' => filled($module),
      ])
    >All</a>
    @foreach ($modules as $key => $label)
      <a
        href="{{ route('admin.queues.index', array_filter([
          'module' => $key,
          'q' => $filters['q'] ?? null,
          'queue' => $filters['queue'] ?? null,
          'date_from' => $filters['date_from'] ?? null,
          'date_to' => $filters['date_to'] ?? null,
          'sort' => $filters['sort'] ?? null,
          'direction' => $filters['direction'] ?? null,
        ])) }}"
        @class([
          'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
          'bg-green-600 text-white' => $module === $key,
          'border border-border text-text-subtle hover:bg-surface' => $module !== $key,
        ])
      >{{ $label }}</a>
    @endforeach
  </div>

  <x-admin.filter-bar
    :action="route('admin.queues.index')"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search job, queue, UUID, error…"
    :date-from="$filters['date_from'] ?? ''"
    :date-to="$filters['date_to'] ?? ''"
    :sort="$filters['sort'] ?? 'id'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:hidden>
      @if ($module)
        <input type="hidden" name="module" value="{{ $module }}">
      @endif
    </x-slot:hidden>
    <x-slot:filters>
      <label class="flex min-w-[160px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Queue name</span>
        <select name="queue" class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary" data-listing-filter>
          <option value="">All queues</option>
          @foreach ($queue_names as $name)
            <option value="{{ $name }}" @selected(($filters['queue'] ?? '') === $name)>{{ $name }}</option>
          @endforeach
        </select>
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>
<section class="p-4 pt-0">
    <h2 class="mb-3 text-lg font-bold">Pending jobs</h2>
    <x-ui.data-table :headers="['Queue', 'Module', 'Job', 'Attempts', 'Available (IST)', 'Created (IST)']" :paginator="$pending">
      @forelse ($pending as $job)
        @php
          $isNewestSort = ($filters['sort'] ?? 'id') === 'id' && ($filters['direction'] ?? 'desc') === 'desc';
          $isLatest = $loop->first && $isNewestSort && $pending->currentPage() === 1;
          $isRecent = ! empty($job['created_at_ts']) && $job['created_at_ts'] >= now()->subHour()->getTimestamp();
        @endphp
        <tr @class([
          'bg-green-50 ring-1 ring-inset ring-green-200' => $isLatest,
          'bg-amber-50/80' => ! $isLatest && $isRecent,
          'bg-elevated' => ! $isLatest && ! $isRecent,
        ])>
          <td class="fd-table-cell p-2 align-middle text-sm">
            <div class="flex flex-col gap-1">
              <span>{{ $job['queue'] }}</span>
              @if ($isLatest)
                <span class="inline-flex w-fit rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Latest</span>
              @elseif ($isRecent)
                <span class="inline-flex w-fit rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">New</span>
              @endif
            </div>
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs font-semibold">{{ $modules[$job['module']] ?? $job['module'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $job['display_name'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $job['attempts'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $job['available_at'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">{{ $job['created_at'] }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No pending jobs{{ $module ? ' for this module' : '' }}.</td></tr>
      @endforelse
    </x-ui.data-table>
  </section>

  <section class="p-4 pt-0">
    <h2 class="mb-3 text-lg font-bold">Failed jobs</h2>
    <x-ui.data-table :headers="['UUID', 'Queue', 'Module', 'Job', 'Error', 'Failed (IST)', 'Actions']" :paginator="$failed">
      @forelse ($failed as $job)
        @php
          $isNewestSort = in_array(($filters['sort'] ?? 'id'), ['id', 'failed_at'], true) && ($filters['direction'] ?? 'desc') === 'desc';
          $isLatest = $loop->first && $isNewestSort && $failed->currentPage() === 1;
          $isRecent = ! empty($job['failed_at_ts']) && $job['failed_at_ts'] >= now()->subHour()->getTimestamp();
        @endphp
        <tr @class([
          'align-top',
          'bg-green-50 ring-1 ring-inset ring-green-200' => $isLatest,
          'bg-amber-50/80' => ! $isLatest && $isRecent,
          'bg-elevated' => ! $isLatest && ! $isRecent,
        ])>
          <td class="fd-table-cell p-2 align-middle font-mono text-xs">{{ \Illuminate\Support\Str::limit($job['uuid'], 13, '…') }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm">{{ $job['queue'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-xs font-semibold">{{ $modules[$job['module']] ?? $job['module'] }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm font-semibold">{{ $job['display_name'] }}</td>
          <td class="fd-table-cell max-w-md p-2 align-middle text-xs text-text-subtle">
            <details>
              <summary class="cursor-pointer text-xs font-semibold text-green-600 hover:underline">
                {{ \Illuminate\Support\Str::of($job['exception'])->before("\n")->limit(120) }}
              </summary>
              <pre class="mt-1 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded bg-surface p-2 text-[11px] leading-relaxed text-text-primary">{{ $job['exception'] }}</pre>
            </details>
          </td>
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle">
            <div class="flex flex-col gap-1">
              <span>{{ $job['failed_at'] }}</span>
              @if ($isLatest)
                <span class="inline-flex w-fit rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Latest</span>
              @elseif ($isRecent)
                <span class="inline-flex w-fit rounded bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">New</span>
              @endif
            </div>
          </td>
          <td class="w-[120px] p-2 align-middle">
            <div class="flex items-center justify-center gap-6">
              <form method="POST" action="{{ route('admin.queues.retry', $job['uuid']) }}" class="inline">
                @csrf
                <button type="submit" class="flex size-5 items-center justify-center" aria-label="Retry" title="Retry">
                  <img src="{{ asset('images/campaigns/refresh.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </form>
              <form method="POST" action="{{ route('admin.queues.forget', $job['uuid']) }}" class="inline">
                @csrf
                <button type="submit" class="flex size-5 items-center justify-center" aria-label="Forget" title="Forget">
                  <img src="{{ asset('images/campaigns/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="p-6 text-center text-sm text-text-subtle">No failed jobs{{ $module ? ' for this module' : '' }}.</td></tr>
      @endforelse
    </x-ui.data-table>
  </section>
</x-admin.layout>
