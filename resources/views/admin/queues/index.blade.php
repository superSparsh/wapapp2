<x-admin.layout title="Queues - Admin" active="admin.queues.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Queues</h1>
      <p class="text-sm text-text-subtle opacity-70">
        Connection <span class="font-semibold">{{ $connection }}</span>
        ({{ $driver }}) — pending {{ $pending_count }}, failed {{ $failed_count }}.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('admin.queues.retry-all') }}">@csrf
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Retry all failed</button>
      </form>
      <form method="POST" action="{{ route('admin.queues.flush') }}" data-confirm="Flush all failed jobs?" data-confirm-variant="danger">@csrf
        <button class="rounded-lg bg-red-500 px-3 py-2 text-xs font-semibold text-white">Flush failed</button>
      </form>
    </div>
  </div>

  @if (session('status'))
    <div class="mx-4 mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
  @endif

  <section class="p-4 pt-0">
    <h2 class="mb-3 text-lg font-bold">Pending jobs</h2>
    <x-ui.data-table :headers="['Queue', 'Job', 'Attempts', 'Available', 'Created']" :paginator="$pending">
      @forelse ($pending as $job)
        <tr>
          <td class="p-3 text-sm">{{ $job['queue'] }}</td>
          <td class="p-3 text-sm font-semibold">{{ $job['display_name'] }}</td>
          <td class="p-3 text-sm">{{ $job['attempts'] }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ $job['available_at'] }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ $job['created_at'] }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="p-6 text-center text-sm text-text-subtle">No pending jobs in the database queue table.</td></tr>
      @endforelse
    </x-ui.data-table>
  </section>

  <section class="p-4 pt-0">
    <h2 class="mb-3 text-lg font-bold">Failed jobs</h2>
    <x-ui.data-table :headers="['UUID', 'Queue', 'Job', 'Error', 'Failed', '']" :paginator="$failed">
      @forelse ($failed as $job)
        <tr>
          <td class="p-3 font-mono text-xs">{{ \Illuminate\Support\Str::limit($job['uuid'], 13, '…') }}</td>
          <td class="p-3 text-sm">{{ $job['queue'] }}</td>
          <td class="p-3 text-sm font-semibold">{{ $job['display_name'] }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ $job['exception'] }}</td>
          <td class="p-3 text-xs text-text-subtle">{{ $job['failed_at'] }}</td>
          <td class="p-3">
            <div class="flex gap-2">
              <form method="POST" action="{{ route('admin.queues.retry', $job['uuid']) }}">@csrf
                <button class="text-xs font-semibold text-green-600 hover:underline">Retry</button>
              </form>
              <form method="POST" action="{{ route('admin.queues.forget', $job['uuid']) }}">@csrf
                <button class="text-xs font-semibold text-red-600 hover:underline">Forget</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-sm text-text-subtle">No failed jobs.</td></tr>
      @endforelse
    </x-ui.data-table>
  </section>
</x-admin.layout>
