<x-admin.layout :title="$title.' - Admin'" :active="'admin.'.$module.'.index'">
  <div class="p-4">
    <h1 class="text-2xl font-bold text-text-primary">{{ $title }}</h1>
    <p class="mt-2 max-w-2xl text-sm text-text-subtle">
      This module is wired in the admin navigation to match legacy backoffice coverage.
      Full operational UI will be filled next using the same Final Design theme and existing platform data.
    </p>
  </div>

  <div class="mx-4 mb-8 rounded-[20px] border border-dashed border-border bg-elevated p-6">
    <p class="text-sm font-semibold text-text-primary">Coming in the next admin slice</p>
    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-text-subtle">
      <li>Legacy-parity workflows for {{ $title }}</li>
      <li>Filters, exports, and action forms</li>
      <li>Same shell, tokens, and table patterns as Customers / Plans</li>
    </ul>
    <a href="{{ route('admin.dashboard') }}" class="mt-5 inline-flex text-sm font-semibold text-green-600 hover:underline">Back to dashboard</a>
  </div>
</x-admin.layout>
