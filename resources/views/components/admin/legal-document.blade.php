@props([
  'title',
  'subtitle' => null,
  'active',
  'effectiveDate' => null,
])

<x-admin.layout :title="$title.' - Admin'" :active="$active">
  <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
        <a href="{{ route('admin.dpdp-readiness') }}" class="text-primary-2 underline">← DPDP readiness</a>
      </p>
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $title }}</h1>
      @if ($subtitle)
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">{{ $subtitle }}</p>
      @endif
      @if ($effectiveDate)
        <p class="text-xs font-medium text-text-muted">Effective date: {{ $effectiveDate }}</p>
      @endif
    </div>

    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      Admin preview of the same draft customers see when signed in. Not linked on public login/footer until
      <code class="text-xs">LEGAL_PUBLIC_PUBLISH=true</code>.
    </div>

    <article class="rounded-[20px] border border-border-light bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)] sm:p-8">
      <div class="legal-doc space-y-6 text-sm leading-[1.6] text-text-body">
        {{ $slot }}
      </div>
    </article>

    <nav class="flex flex-wrap gap-3 text-sm font-semibold">
      <a href="{{ route('admin.legal.privacy') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('admin.legal.privacy'), 'text-text-muted' => request()->routeIs('admin.legal.privacy')])>Privacy Policy</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('admin.legal.terms') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('admin.legal.terms'), 'text-text-muted' => request()->routeIs('admin.legal.terms')])>Terms of Service</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('admin.legal.breach') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('admin.legal.breach'), 'text-text-muted' => request()->routeIs('admin.legal.breach')])>Breach playbook</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('admin.legal.collection-notice') }}" @class(['underline', 'text-primary-2' => ! request()->routeIs('admin.legal.collection-notice'), 'text-text-muted' => request()->routeIs('admin.legal.collection-notice')])>Collection notice</a>
    </nav>
  </div>
</x-admin.layout>
