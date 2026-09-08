<x-admin.layout :title="$title.' - Admin'" active="admin.submissions.index">
  <div class="p-4">
    <a href="{{ route('admin.submissions.index', ['tab' => $tab]) }}" class="text-xs font-semibold text-green-600 hover:underline">← Submissions</a>
    <h1 class="mt-1 text-2xl font-bold text-text-primary">{{ $title }}</h1>
  </div>

  <section class="mx-4 mb-4 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
      @foreach ($fields as $label => $value)
        <div>
          <dt class="text-xs uppercase tracking-wide text-text-subtle">{{ $label }}</dt>
          <dd class="mt-1 font-semibold">{{ $value ?: '—' }}</dd>
        </div>
      @endforeach
    </dl>

    @if ($resendUrl)
      <form method="POST" action="{{ $resendUrl }}" class="mt-5">
        @csrf
        <button class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Resend to Zoho</button>
      </form>
    @endif
  </section>

  <section class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    <h2 class="text-lg font-bold">Submitted payload</h2>
    @if (empty($payload))
      <p class="mt-3 text-sm text-text-subtle">No extra payload stored.</p>
    @else
      <pre class="mt-3 overflow-x-auto rounded-lg bg-surface p-3 text-xs">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    @endif
  </section>
</x-admin.layout>
