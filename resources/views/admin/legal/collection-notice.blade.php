<x-admin.layout title="Collection notice - Admin" active="admin.legal.collection-notice">
  <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
      <a href="{{ route('admin.dpdp-readiness') }}" class="text-primary-2 underline">← DPDP readiness</a>
    </p>
    <h1 class="text-2xl font-bold text-text-primary">Collection-point notice</h1>
    <p class="text-sm text-text-subtle">
      This is the same notice shown on customer signup / privacy-rights forms.
    </p>
    <x-legal.collection-notice />
    <nav class="flex flex-wrap gap-3 text-sm font-semibold">
      <a href="{{ route('admin.legal.privacy') }}" class="text-primary-2 underline">Privacy Policy</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('admin.legal.terms') }}" class="text-primary-2 underline">Terms of Service</a>
      <span class="text-text-muted">·</span>
      <a href="{{ route('admin.legal.breach') }}" class="text-primary-2 underline">Breach playbook</a>
    </nav>
  </div>
</x-admin.layout>
