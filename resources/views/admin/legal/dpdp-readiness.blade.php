<x-admin.layout title="DPDP readiness - Admin" active="admin.dpdp-readiness">
  @php
    $statusStyles = [
      'done' => 'bg-green-50 text-green-700 ring-green-200',
      'partial' => 'bg-amber-50 text-amber-800 ring-amber-200',
      'pending' => 'bg-surface text-text-muted ring-border',
    ];
    $statusLabels = [
      'done' => 'Done',
      'partial' => 'Partial',
      'pending' => 'Pending',
    ];
  @endphp

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-text-primary">DPDP / Legal readiness</h1>
        <p class="mt-1 max-w-3xl text-sm text-text-subtle">
          Platform checklist for Tekpro / WapApp DPDP building blocks. Privacy &amp; Terms stay
          <span class="font-semibold text-text-primary">internal draft</span> (login-gated) until leadership publishes on public login/footer.
          Business login continues via forms.tekprocloud.com onboarding → credentials.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-200">{{ $summary['done'] }} done</span>
        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">{{ $summary['partial'] }} partial</span>
        <span class="inline-flex items-center rounded-full bg-surface px-3 py-1 text-xs font-semibold text-text-muted ring-1 ring-inset ring-border">{{ $summary['pending'] }} pending</span>
      </div>
    </div>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Configured contacts</h2>
      <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Company</dt>
          <dd class="mt-1 text-sm font-medium text-text-primary">{{ $config['company'] }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Product</dt>
          <dd class="mt-1 text-sm font-medium text-text-primary">{{ $config['product'] }}</dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Grievance</dt>
          <dd class="mt-1 text-sm font-medium text-text-primary">
            <a href="mailto:{{ $config['grievance_email'] }}" class="underline">{{ $config['grievance_email'] }}</a>
          </dd>
        </div>
        <div>
          <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">Effective</dt>
          <dd class="mt-1 text-sm font-medium text-text-primary">{{ $config['effective_date'] }}</dd>
        </div>
      </dl>
    </section>

    <section class="rounded-[20px] border border-border bg-elevated p-5">
      <h2 class="text-lg font-bold text-text-primary">Checklist</h2>
      <ul class="mt-4 divide-y divide-border">
        @foreach ($items as $item)
          <li class="flex flex-col gap-2 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-semibold text-text-primary">{{ $item['label'] }}</p>
              <p class="mt-1 text-sm text-text-body">{{ $item['detail'] }}</p>
              @if (! empty($item['href']))
                <a href="{{ $item['href'] }}" target="_blank" rel="noopener" class="mt-2 inline-flex text-xs font-semibold text-primary-2 underline">Open</a>
              @endif
            </div>
            <span @class([
              'inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
              $statusStyles[$item['status']] ?? $statusStyles['pending'],
            ])>{{ $statusLabels[$item['status']] ?? 'Pending' }}</span>
          </li>
        @endforeach
      </ul>
    </section>

    <p class="text-xs text-text-muted">
      Per-customer signed PDF + custom terms status also appears on each Admin → Customer detail page.
    </p>
  </div>
</x-admin.layout>
