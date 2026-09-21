<x-admin.layout title="Server Ops - Admin" active="admin.queues.index">
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

  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Server ops</h1>
      <p class="text-sm text-text-subtle">
        Allowlisted commands for queue, Horizon, Laravel cache, Redis, Supervisor, and Apache.
        Shell actions need passwordless sudo for the PHP user.
      </p>
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
      <a href="{{ route('admin.queues.index') }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Open Queues</a>
      <form method="POST" action="{{ route('admin.server-ops.run') }}" data-confirm="Send queue:restart?">
        @csrf
        <input type="hidden" name="command" value="queue_restart">
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Restart workers</button>
      </form>
      <form method="POST" action="{{ route('admin.server-ops.run') }}" data-confirm="Terminate Horizon?" data-confirm-variant="danger">
        @csrf
        <input type="hidden" name="command" value="horizon_terminate">
        <button class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100">Terminate Horizon</button>
      </form>
      <form method="POST" action="{{ route('admin.server-ops.run') }}" data-confirm="supervisorctl restart horizon?" data-confirm-variant="danger">
        @csrf
        <input type="hidden" name="command" value="supervisor_restart_horizon">
        <button class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">Restart Horizon (Supervisor)</button>
      </form>
    </div>
  </div>

  @if (session('ops_output'))
    <div class="mx-4 mb-4 rounded-xl border border-border bg-elevated p-4">
      <p class="text-xs font-semibold uppercase tracking-wide text-text-subtle">Last output @if (session('ops_command')) ({{ session('ops_command') }}) @endif</p>
      <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-words text-xs leading-relaxed text-text-primary">{{ session('ops_output') }}</pre>
    </div>
  @endif

  <section class="space-y-4 p-4 pt-0">
    <div class="grid gap-4 xl:grid-cols-2">
      @foreach ($ops_groups as $group => $commands)
        <div class="rounded-xl border border-border bg-elevated p-4">
          <h2 class="text-sm font-bold text-text-primary">{{ $group }}</h2>
          <ul class="mt-3 divide-y divide-border">
            @foreach ($commands as $cmd)
              <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-text-primary">{{ $cmd['label'] }}</p>
                  <p class="mt-0.5 text-xs text-text-subtle">{{ $cmd['description'] }}</p>
                </div>
                <form method="POST" action="{{ route('admin.server-ops.run') }}"
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
</x-admin.layout>
