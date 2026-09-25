@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.faqs.update', $row) : route('admin.faqs.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit FAQ' : 'Add FAQ').' - Admin'" active="admin.faqs.index">
  <div class="p-4">
    <a href="{{ route('admin.faqs.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← FAQs</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit FAQ' : 'Add FAQ' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Heading</span>
        <input name="heading" value="{{ old('heading', $row->heading ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Slug (optional)</span>
        <input name="slug" value="{{ old('slug', $row->slug ?? '') }}" class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Answer (HTML allowed)</span>
        <textarea name="description" rows="12" required class="rounded-lg border border-border px-3 py-2 font-mono text-xs">{{ old('description', $row->description ?? '') }}</textarea>
        <span class="text-xs text-text-subtle">Legacy FAQs use HTML (&lt;h3&gt;, &lt;p&gt;, lists). Customers see rendered HTML on /faqs.</span>
      </label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Sort order</span>
          <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $row->sort_order ?? 0) }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="inline-flex items-center gap-2 self-end text-sm">
          <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
          <span class="font-semibold">Active</span>
        </label>
      </div>
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
