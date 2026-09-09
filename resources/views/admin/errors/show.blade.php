<x-admin.layout title="{{ $moduleLabel }} Errors - Admin" active="admin.errors.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.errors.index') }}" class="text-xs font-semibold text-green-600 hover:underline">All modules</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $moduleLabel }}</h1>
      <p class="text-sm text-text-subtle">Module errors only — switch tabs to filter by type.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a
        href="{{ route('admin.queues.index', ['module' => $module]) }}"
        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface"
      >View queues</a>
      <form method="POST" action="{{ route('admin.errors.clear', $module) }}" data-confirm="Clear errors older than 30 days for this module?" data-confirm-variant="danger">
        @csrf
        <input type="hidden" name="days" value="30">
        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
          Clear &gt;30 days
        </button>
      </form>
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
    @foreach (['all' => 'All', 'exception' => 'Exception', 'api' => 'API', 'job' => 'Job'] as $key => $label)
      <a
        href="{{ route('admin.errors.show', ['module' => $module, 'type' => $key, 'tenant_id' => $tenantId]) }}"
        @class([
          'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
          'bg-green-600 text-white' => $type === $key,
          'border border-border text-text-subtle hover:bg-surface' => $type !== $key,
        ])
      >{{ $label }}</a>
    @endforeach

    <form method="GET" action="{{ route('admin.errors.show', $module) }}" class="ml-auto flex items-center gap-2">
      <input type="hidden" name="type" value="{{ $type }}">
      <input
        type="text"
        name="tenant_id"
        value="{{ $tenantId }}"
        placeholder="Tenant ID"
        class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-text-primary"
      >
      <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold hover:bg-surface">Filter</button>
    </form>
  </div>

  <section class="p-4 pt-0">
    <x-ui.data-table :headers="['When', 'Type', 'Source', 'Message', 'Tenant']" :paginator="$logs">
      @forelse ($logs as $log)
        <tr class="align-top">
          <td class="p-3 text-xs text-text-subtle whitespace-nowrap">{{ $log->occurred_at?->format('Y-m-d H:i') }}</td>
          <td class="p-3">
            <span class="rounded bg-surface px-2 py-0.5 text-xs font-semibold uppercase text-text-primary">
              {{ $log->type instanceof \App\Enums\PlatformErrorType ? $log->type->value : $log->type }}
            </span>
          </td>
          <td class="p-3 font-mono text-xs text-text-subtle break-all">{{ $log->source ?? '—' }}</td>
          <td class="p-3 text-sm text-text-primary">
            <div>{{ \Illuminate\Support\Str::limit($log->message, 180) }}</div>
            @if (! empty($log->context))
              <details class="mt-1">
                <summary class="cursor-pointer text-xs text-green-600">Context</summary>
                <pre class="mt-1 max-h-40 overflow-auto rounded bg-surface p-2 text-[11px] text-text-subtle">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
              </details>
            @endif
          </td>
          <td class="p-3 font-mono text-xs text-text-subtle">{{ $log->tenant_id ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="p-6 text-center text-sm text-text-subtle">No errors for this module{{ $type !== 'all' ? " ({$type})" : '' }}.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </section>
</x-admin.layout>
