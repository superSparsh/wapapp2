<x-admin.layout title="Tittu Gateway - Admin" active="admin.tittu-gateway.index">
  @php
    $up = (bool) $snapshot['up'];
    $summary = $snapshot['summary'];
    $stateLabel = [
      'active' => 'Active',
      'cooling' => 'Cooling',
      'key_missing' => 'Key not set',
      'disabled' => 'Disabled',
    ];
  @endphp

  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Tittu Gateway</h1>
      <p class="text-sm text-text-subtle opacity-70">{{ $snapshot['url'] }} · checked {{ $snapshot['checked_at'] }}</p>
      <div class="mt-2 flex flex-wrap items-center gap-2">
        <span @class([
          'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
          'bg-green-50 text-green-700 ring-green-200' => $up,
          'bg-red-50 text-red-700 ring-red-200' => ! $up,
        ])>{{ $up ? 'Up' : 'Down' }}</span>
        @if ($snapshot['latency_ms'] !== null)
          <span class="inline-flex items-center rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-text-subtle ring-1 ring-inset ring-border">{{ $snapshot['latency_ms'] }} ms</span>
        @endif
        @if (is_array($summary))
          <span @class([
            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
            'bg-green-50 text-green-700 ring-green-200' => ($summary['providers_cooling'] ?? 0) === 0 && ($summary['status'] ?? '') === 'ok',
            'bg-amber-50 text-amber-700 ring-amber-200' => ($summary['providers_cooling'] ?? 0) > 0 || ($summary['status'] ?? '') !== 'ok',
          ])>{{ $summary['providers_active'] }} / {{ $summary['providers_total'] }} providers active</span>
        @endif
      </div>
      @if ($snapshot['health_error'])
        <p class="mt-2 text-sm text-red-600">{{ $snapshot['health_error'] }}</p>
      @endif
    </div>
    <a href="{{ route('admin.tittu-gateway.index') }}" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">Check again</a>
  </div>

  @if (is_array($summary))
    <section class="grid gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-semibold text-text-subtle">Gateway status</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ $summary['status'] }}</p>
      </div>
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-semibold text-text-subtle">Client keys active</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ $summary['active_keys'] }}</p>
      </div>
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-semibold text-text-subtle">Requests today</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format((int) $summary['requests_today']) }}</p>
      </div>
      <div class="rounded-[20px] border border-border bg-elevated p-4">
        <p class="text-xs font-semibold text-text-subtle">This minute</p>
        <p class="mt-1 text-2xl font-bold text-text-primary">{{ number_format((int) $summary['requests_minute']) }}</p>
      </div>
    </section>
  @elseif ($snapshot['detail_error'])
    <p class="mx-4 mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $snapshot['detail_error'] }}</p>
  @endif

  @if ($snapshot['providers'] !== [])
    <div class="mx-4 mb-6 overflow-x-auto rounded-[20px] border border-border bg-elevated">
      <table class="min-w-full text-left text-sm">
        <thead class="border-b border-border text-xs font-semibold text-text-subtle">
          <tr>
            <th class="px-4 py-3">Provider</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Today</th>
            <th class="px-4 py-3">Limits</th>
            <th class="px-4 py-3">Models</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($snapshot['providers'] as $provider)
            <tr class="border-b border-border last:border-0">
              <td class="px-4 py-3">
                <p class="font-semibold text-text-primary">#{{ $provider['priority'] }} {{ $provider['id'] }}</p>
                <p class="text-xs text-text-subtle">{{ $provider['adapter'] }}
                  @if ($provider['accounts_total'] > 0)
                    · {{ $provider['accounts_ready'] }}/{{ $provider['accounts_total'] }} accounts ready
                  @endif
                </p>
              </td>
              <td class="px-4 py-3">
                <span @class([
                  'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                  'bg-green-50 text-green-700' => $provider['state'] === 'active',
                  'bg-amber-50 text-amber-700' => $provider['state'] === 'cooling',
                  'bg-red-50 text-red-700' => $provider['state'] === 'key_missing',
                  'bg-surface text-text-subtle' => $provider['state'] === 'disabled',
                ])>{{ $stateLabel[$provider['state']] ?? $provider['state'] }}</span>
                @if ($provider['state'] === 'cooling')
                  <p class="mt-1 text-xs text-amber-700">{{ $provider['cooldown_reason'] !== '' ? $provider['cooldown_reason'].' · ' : '' }}{{ $provider['cooldown_seconds'] }}s left</p>
                @endif
              </td>
              <td class="px-4 py-3 text-text-primary">{{ number_format((int) $provider['requests_today']) }}</td>
              <td class="px-4 py-3 text-xs text-text-subtle">
                @if ($provider['limit_rpm']) {{ $provider['requests_minute'] }}/{{ $provider['limit_rpm'] }} per min @endif
                @if ($provider['limit_rpd']) <span class="block">{{ $provider['limit_rpd'] }}/day</span> @endif
              </td>
              <td class="px-4 py-3 text-xs text-text-subtle">{{ $provider['models'] !== [] ? implode(', ', array_slice($provider['models'], 0, 3)) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.tittu-gateway.update') }}" class="mx-4 mb-8 max-w-xl space-y-3 rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @method('PUT')
    <h2 class="text-lg font-bold text-text-primary">Status token</h2>
    <p class="text-sm text-text-subtle">
      Up or down comes from the public health check.
      Provider activity needs a bearer token that can read the gateway operator status.
      @if ($snapshot['token_source'] === 'admin')
        A token is saved in admin.
      @elseif ($snapshot['token_source'] === 'env')
        A token is set on the server.
      @else
        No token is set.
      @endif
    </p>
    <label class="flex flex-col gap-1.5 text-sm">
      <span class="font-semibold">New token</span>
      <input type="password" name="status_token" autocomplete="new-password" class="rounded-lg border border-border px-3 py-2" placeholder="Leave blank to keep the current token">
    </label>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="clear_status_token" value="1" class="rounded border-border">
      <span>Remove the saved admin token</span>
    </label>
    @error('status_token')
      <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
    <button class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save token</button>
  </form>
</x-admin.layout>
