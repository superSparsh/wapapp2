@php
  $isEdit = isset($row);
  $action = $isEdit ? route('admin.platform-templates.update', $row) : route('admin.platform-templates.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit template' : 'Add template').' - Admin'" active="admin.platform-templates.index">
  <div class="p-4">
    <a href="{{ route('admin.platform-templates.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Template gallery</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit template' : 'Add template' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Name</span>
        <input name="name" value="{{ old('name', $row->name ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Category</span>
        <input name="category" value="{{ old('category', $row->category ?? '') }}" class="rounded-lg border border-border px-3 py-2" placeholder="marketing">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Type</span>
        <select name="type" class="rounded-lg border border-border px-3 py-2">
          @foreach ($types as $type)
            <option value="{{ $type }}" @selected(old('type', $row->type ?? 'whatsapp') === $type)>{{ ucfirst($type) }}</option>
          @endforeach
        </select>
      </label>
      <label class="inline-flex items-center gap-2 self-end text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $row->is_active ?? true))>
        <span class="font-semibold">Active</span>
      </label>
      <label class="flex flex-col gap-1.5 text-sm sm:col-span-2">
        <span class="font-semibold">Body</span>
        <textarea name="body" rows="8" class="rounded-lg border border-border px-3 py-2">{{ old('body', $row->body ?? '') }}</textarea>
      </label>
    </div>

    <button class="mt-6 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Save' }}</button>
  </form>
</x-admin.layout>
