<x-admin.layout title="{{ $moduleLabel }} Errors - Admin" active="admin.errors.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <a href="{{ route('admin.errors.index') }}" class="text-xs font-semibold text-green-600 hover:underline">All modules</a>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $moduleLabel }}</h1>
      <p class="text-sm text-text-subtle">Search, date range, type, and tenant filters for this module.</p>
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
        href="{{ route('admin.errors.show', array_filter([
          'module' => $module,
          'type' => $key,
          'tenant_id' => $filters['tenant_id'] ?? null,
          'q' => $filters['q'] ?? null,
          'date_from' => $filters['date_from'] ?? null,
          'date_to' => $filters['date_to'] ?? null,
          'sort' => $filters['sort'] ?? null,
          'direction' => $filters['direction'] ?? null,
        ])) }}"
        @class([
          'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
          'bg-green-600 text-white' => $type === $key,
          'border border-border text-text-subtle hover:bg-surface' => $type !== $key,
        ])
      >{{ $label }}</a>
    @endforeach
  </div>

  <x-admin.filter-bar
    :action="route('admin.errors.show', $module)"
    :search="$filters['q'] ?? ''"
    search-placeholder="Search message, source, tenant…"
    :date-from="$filters['date_from'] ?? ''"
    :date-to="$filters['date_to'] ?? ''"
    :sort="$filters['sort'] ?? 'occurred_at'"
    :direction="$filters['direction'] ?? 'desc'"
    :sort-options="$sortOptions"
  >
    <x-slot:hidden>
      <input type="hidden" name="type" value="{{ $type }}">
    </x-slot:hidden>
    <x-slot:filters>
      <label class="flex min-w-[160px] flex-col gap-1.5 text-sm">
        <span class="font-semibold text-text-primary">Tenant ID</span>
        <input
          type="text"
          name="tenant_id"
          value="{{ $filters['tenant_id'] ?? '' }}"
          placeholder="Exact tenant id"
          class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        >
      </label>
    </x-slot:filters>
  </x-admin.filter-bar>

  <section class="p-4 pt-0">
    <x-ui.data-table :headers="['When', 'Type', 'Source', 'Message', 'Tenant']" :paginator="$logs">
      @forelse ($logs as $log)
        <tr class="bg-elevated align-top">
          <td class="fd-table-cell p-2 align-middle text-xs text-text-subtle whitespace-nowrap">{{ format_ist($log->occurred_at) }}</td>
          <td class="fd-table-cell p-2 align-middle">
            <span class="rounded bg-surface px-2 py-0.5 text-xs font-semibold uppercase text-text-primary">
              {{ $log->type instanceof \App\Enums\PlatformErrorType ? $log->type->value : $log->type }}
            </span>
          </td>
          <td class="fd-table-cell p-2 align-middle font-mono text-xs text-text-subtle break-all">{{ $log->source ?? '—' }}</td>
          <td class="fd-table-cell p-2 align-middle text-sm text-text-primary">
            <div>{{ \Illuminate\Support\Str::limit($log->message, 180) }}</div>
            @if (! empty($log->context))
              <details class="mt-1">
                <summary class="cursor-pointer text-xs text-green-600">Context</summary>
                <pre class="mt-1 max-h-40 overflow-auto rounded bg-surface p-2 text-[11px] text-text-subtle">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
              </details>
            @endif
          </td>
          <td class="fd-table-cell p-2 align-middle font-mono text-xs text-text-subtle">{{ $log->tenant_id ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="p-6 text-center text-sm text-text-subtle">No errors for this module{{ $type !== 'all' ? " ({$type})" : '' }}.</td>
        </tr>
      @endforelse
    </x-ui.data-table>
  </section>
</x-admin.layout>
