<x-admin.layout title="Errors - Admin" active="admin.errors.index">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">Errors</h1>
    <p class="mt-1 text-sm text-text-subtle">
      Open one module at a time — exceptions, WhatsApp/CAMS API fails, and queue job failures.
    </p>
  </div>

  <div class="grid gap-3 p-4 pt-0 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @foreach ($modules as $row)
      <a
        href="{{ route('admin.errors.show', $row['module']) }}"
        class="rounded-xl border border-border bg-elevated p-4 transition hover:border-green-500/40 hover:bg-surface"
      >
        <div class="flex items-start justify-between gap-2">
          <h2 class="text-base font-semibold text-text-primary">{{ $row['label'] }}</h2>
          <span class="rounded-md bg-surface px-2 py-0.5 text-xs font-bold text-text-primary">{{ number_format($row['total']) }}</span>
        </div>
        <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs text-text-subtle">
          <div>
            <dt class="opacity-70">Exception</dt>
            <dd class="mt-0.5 font-semibold text-text-primary">{{ number_format($row['exception']) }}</dd>
          </div>
          <div>
            <dt class="opacity-70">API</dt>
            <dd class="mt-0.5 font-semibold text-text-primary">{{ number_format($row['api']) }}</dd>
          </div>
          <div>
            <dt class="opacity-70">Job</dt>
            <dd class="mt-0.5 font-semibold text-text-primary">{{ number_format($row['job']) }}</dd>
          </div>
        </dl>
      </a>
    @endforeach
  </div>
</x-admin.layout>
