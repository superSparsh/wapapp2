<x-admin.layout title="Invoice Preview - Admin" active="admin.invoice-template.edit">
  <div class="p-4">
    <a href="{{ route('admin.invoice-template.edit') }}" class="text-xs font-semibold text-green-600 hover:underline">← Invoice template</a>
    <h1 class="mt-1 text-2xl font-bold text-text-primary">Invoice preview</h1>
    <p class="text-sm text-text-subtle opacity-70">Raw template markup as stored.</p>
  </div>

  <section class="mx-4 mb-8 max-w-4xl rounded-[20px] border border-border bg-elevated p-5">
    @if (trim((string) $template) === '')
      <p class="text-sm text-text-subtle">No custom template saved — the built-in invoice layout is used.</p>
    @else
      <pre class="overflow-x-auto rounded-lg bg-surface p-3 text-xs">{{ $template }}</pre>
    @endif
  </section>
</x-admin.layout>
