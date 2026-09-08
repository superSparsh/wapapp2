@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.currencies.update', $row) : route('admin.currencies.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit currency' : 'Add currency').' - Admin'" active="admin.currencies.index">
  <div class="p-4">
    <a href="{{ route('admin.currencies.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Currencies</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit currency' : 'Add currency' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-col gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $row->name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Code</span>
        <input name="code" maxlength="3" value="{{ old('code', $row->code ?? '') }}" required class="rounded-lg border border-border px-3 py-2" placeholder="INR">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Display format</span>
        <input name="format" value="{{ old('format', $row->format ?? '₹{PRICE}') }}" class="rounded-lg border border-border px-3 py-2">
        <span class="text-xs text-text-subtle">Use <code>{PRICE}</code> as the amount placeholder.</span>
      </label>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
        <span class="font-semibold">Active</span>
      </label>
    </div>

    <button class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
