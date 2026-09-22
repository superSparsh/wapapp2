<x-admin.layout title="Error #{{ $log->id }} - Admin" active="admin.errors.index">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
        <a href="{{ route('admin.errors.index') }}" class="text-green-600 hover:underline">All modules</a>
        <span class="text-text-subtle">/</span>
        <a href="{{ route('admin.errors.show', $module) }}" class="text-green-600 hover:underline">{{ $moduleLabel }}</a>
      </div>
      <h1 class="mt-1 text-2xl font-bold text-text-primary">Error #{{ $log->id }}</h1>
      <p class="text-sm text-text-subtle">{{ format_ist($log->occurred_at) }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a
        href="{{ route('admin.errors.show', $module) }}"
        class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface"
      >Back to module</a>
      <form
        method="POST"
        action="{{ route('admin.errors.destroy', [$module, $log]) }}"
        data-confirm="Delete this error?"
        data-confirm-variant="danger"
      >
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100">
          Delete
        </button>
      </form>
    </div>
  </div>

  <section class="space-y-4 p-4 pt-0">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl border border-border bg-elevated p-3">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Type</div>
        <div class="mt-1 text-sm font-semibold uppercase text-text-primary">
          {{ $log->type instanceof \App\Enums\PlatformErrorType ? $log->type->value : $log->type }}
        </div>
      </div>
      <div class="rounded-xl border border-border bg-elevated p-3">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Module</div>
        <div class="mt-1 text-sm font-semibold text-text-primary">{{ $moduleLabel }}</div>
      </div>
      <div class="rounded-xl border border-border bg-elevated p-3">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Tenant</div>
        <div class="mt-1 break-all font-mono text-sm text-text-primary">{{ $log->tenant_id ?? '—' }}</div>
      </div>
      <div class="rounded-xl border border-border bg-elevated p-3">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Source</div>
        <div class="mt-1 break-all font-mono text-xs text-text-primary">{{ $log->source ?? '—' }}</div>
      </div>
    </div>

    <div class="rounded-xl border border-border bg-elevated p-4">
      <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Message</div>
      <pre class="mt-2 max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-surface p-3 text-xs leading-relaxed text-text-primary">{{ $log->message }}</pre>
    </div>

    @if (! empty($log->context))
      <div class="rounded-xl border border-border bg-elevated p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-text-subtle">Context</div>
        <pre class="mt-2 max-h-[28rem] overflow-auto whitespace-pre-wrap break-words rounded-lg bg-surface p-3 text-xs leading-relaxed text-text-subtle">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
      </div>
    @endif
  </section>
</x-admin.layout>
