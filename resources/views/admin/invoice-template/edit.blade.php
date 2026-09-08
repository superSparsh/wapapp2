<x-admin.layout title="Invoice Template - Admin" active="admin.invoice-template.edit">
  <div class="flex flex-wrap items-start justify-between gap-3 p-4">
    <div>
      <h1 class="text-2xl font-bold text-text-primary">Invoice template</h1>
      <p class="text-sm text-text-subtle opacity-70">Custom HTML used when rendering customer invoices.</p>
    </div>
    <a href="{{ route('admin.invoice-template.preview') }}" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold hover:bg-surface">Preview</a>
  </div>

  <form method="POST" action="{{ route('admin.invoice-template.update') }}" class="mx-4 mb-8 max-w-4xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <label class="flex flex-col gap-1.5 text-sm">
      <span class="font-semibold">Template HTML</span>
      <textarea name="invoice_custom_template" rows="18" class="rounded-lg border border-border px-3 py-2 font-mono text-xs">{{ old('invoice_custom_template', $template) }}</textarea>
      <span class="text-xs text-text-subtle">Leave blank to use the built-in invoice layout.</span>
    </label>

    <button class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">Save template</button>
  </form>
</x-admin.layout>
