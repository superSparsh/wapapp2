@php
  $isEdit = isset($announcement);
  $action = $isEdit ? route('admin.announcements.update', $announcement) : route('admin.announcements.store');
@endphp

<x-admin.layout :title="($isEdit ? 'Edit announcement' : 'New announcement').' - Admin'" active="admin.announcements.index">
  <div class="p-4">
    <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-green-600 hover:underline">← Announcements</a>
    <h1 class="mt-2 text-2xl font-bold text-text-primary">{{ $isEdit ? 'Edit announcement' : 'New announcement' }}</h1>
  </div>

  <form method="POST" action="{{ $action }}" class="mx-4 mb-8 max-w-3xl rounded-[20px] border border-border bg-elevated p-5">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4">
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Title</span>
        <input name="title" value="{{ old('title', $announcement->title ?? '') }}" required class="rounded-lg border border-border px-3 py-2">
      </label>
      <label class="flex flex-col gap-1.5 text-sm">
        <span class="font-semibold">Body</span>
        <textarea name="body" rows="6" required class="rounded-lg border border-border px-3 py-2">{{ old('body', $announcement->body ?? '') }}</textarea>
      </label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Starts at</span>
          <input type="datetime-local" name="starts_at" value="{{ old('starts_at', isset($announcement?->starts_at) ? $announcement->starts_at->format('Y-m-d\\TH:i') : '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
        <label class="flex flex-col gap-1.5 text-sm">
          <span class="font-semibold">Ends at</span>
          <input type="datetime-local" name="ends_at" value="{{ old('ends_at', isset($announcement?->ends_at) ? $announcement->ends_at->format('Y-m-d\\TH:i') : '') }}" class="rounded-lg border border-border px-3 py-2">
        </label>
      </div>
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->is_active ?? true))>
        <span class="font-semibold">Active</span>
      </label>
    </div>

    <button class="mt-5 rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white">{{ $isEdit ? 'Update' : 'Create' }}</button>
  </form>
</x-admin.layout>
